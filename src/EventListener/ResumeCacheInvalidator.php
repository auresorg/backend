<?php

namespace App\EventListener;

use App\Entity\{Project, Experience, Certification, Award, Education, User};
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\{PostPersistEventArgs, PostUpdateEventArgs, PreRemoveEventArgs};
use Doctrine\ORM\Events;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsDoctrineListener(event: Events::postPersist, priority: 500, connection: 'default')]
#[AsDoctrineListener(event: Events::postUpdate, priority: 500, connection: 'default')]
#[AsDoctrineListener(event: Events::preRemove, priority: 500, connection: 'default')]
class ResumeCacheInvalidator
{
    private array $typeMap = [
        Project::class => 'project',
        Experience::class => 'experience',
        Certification::class => 'certification',
        Award::class => 'award',
    ];

    private array $ignoredUserFields = [
        'skills',
        'projectsCount',
        'certCount',
        'awardsCount',
        'experienceCount',
        'projects',
        'experiences',
        'certifications',
        'awards',
    ];

    public function __construct(
        private HttpClientInterface $http,
        private Connection $db,
        private string $serverlessUrl
    ) {
        error_log('[ResumeCache] constructed');
    }

    /* ----------------- HTTP ----------------- */

    private function fire(array $payload): void
    {
        $payload['_call_id'] = uniqid('php_', true);

        error_log('[ResumeCache] FIRE start ' . json_encode($payload));

        $ch = curl_init($this->serverlessUrl);

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->serverlessUrl,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),

            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_NOBODY => false,

            CURLOPT_CONNECTTIMEOUT_MS => 1500,
            CURLOPT_TIMEOUT_MS => 1500,

            CURLOPT_FORBID_REUSE => true,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_NOSIGNAL => true,
        ]);

        $ok = curl_exec($ch);

        if ($ok === false) {
            error_log('[ResumeCache] CURL ERROR: ' . curl_error($ch));
        } else {
            error_log('[ResumeCache] CURL SENT');
        }

        curl_close($ch);
    }

    private function triggerStandard(int $userId, string $username, string $role): void
    {
        error_log("[ResumeCache] triggerStandard userId=$userId role=$role");

        $this->fire([
            'type' => 'standard',
            'userId' => $userId,
            'username' => $username,
            'role' => $role,
        ]);
    }

    private function triggerCustom(int $userId, string $type, int $entityId): void
    {
        error_log("[ResumeCache] triggerCustom userId=$userId type=$type entityId=$entityId");

        $column = match ($type) {
            'project' => 'projects',
            'experience' => 'experiences',
            'certification' => 'certifications',
            'award' => 'awards',
            default => null
        };

        if (!$column) {
            error_log('[ResumeCache] triggerCustom skipped (no column)');
            return;
        }

        $sql = "SELECT slug FROM cusres
                WHERE user_id = :uid
                AND $column::jsonb @> :id::jsonb";

        $rows = $this->db->fetchAllAssociative($sql, [
            'uid' => $userId,
            'id' => json_encode([$entityId]),
        ]);

        error_log('[ResumeCache] triggerCustom rows=' . count($rows));

        foreach ($rows as $row) {
            error_log('[ResumeCache] triggerCustom firing slug=' . $row['slug']);

            $this->fire([
                'type' => 'custom',
                'userId' => $userId,
                'slug' => $row['slug'],
            ]);
        }
    }

    private function triggerContentRoles(User $user): void
    {
        error_log('[ResumeCache] triggerContentRoles userId=' . $user->getId());

        $sql = "
            SELECT role FROM resumes
            WHERE user_id = :id
            AND (projects + certificates + awards + experience) > 0
        ";

        $rows = $this->db->fetchAllAssociative($sql, ['id' => $user->getId()]);

        error_log('[ResumeCache] triggerContentRoles rows=' . count($rows));

        foreach ($rows as $row) {
            $this->triggerStandard(
                $user->getId(),
                $user->getUsername(),
                $row['role']
            );
        }
    }

    /* ----------------- EVENTS ----------------- */

    public function postPersist(PostPersistEventArgs $e): void
    {
        error_log('[ResumeCache] postPersist ' . get_class($e->getObject()));
        $this->handleDomainEntity($e->getObject(), null);
    }

    public function preRemove(PreRemoveEventArgs $e): void
    {
        error_log('[ResumeCache] preRemove ' . get_class($e->getObject()));
        $this->handleDomainEntity($e->getObject(), null);
    }

    public function postUpdate(PostUpdateEventArgs $e): void
    {
        $entity = $e->getObject();

        error_log('[ResumeCache] postUpdate ' . get_class($entity));

        $uow = $e->getObjectManager()->getUnitOfWork();
        $changeSet = $uow->getEntityChangeSet($entity);

        error_log('[ResumeCache] changeset ' . json_encode(array_keys($changeSet)));

        if ($entity instanceof User) {
            error_log('[ResumeCache] handling User');

            foreach ($uow->getScheduledEntityUpdates() as $updated) {
                if (isset($this->typeMap[$updated::class])) {
                    error_log('[ResumeCache] User skipped (domain update present)');
                    return;
                }
            }

            $meaningful = array_diff(array_keys($changeSet), $this->ignoredUserFields);

            if (!$meaningful) {
                error_log('[ResumeCache] User skipped (no meaningful fields)');
                return;
            }

            $this->triggerContentRoles($entity);
            return;
        }

        if ($entity instanceof Education) {
            error_log('[ResumeCache] handling Education');
            $this->triggerContentRoles($entity->getUser());
            return;
        }

        $oldRole = $changeSet['role'][0] ?? null;
        $this->handleDomainEntity($entity, $oldRole);
    }

    private function handleDomainEntity(object $entity, ?string $oldRole): void
    {
        error_log('[ResumeCache] handleDomainEntity ' . get_class($entity));

        if (!isset($this->typeMap[$entity::class])) {
            error_log('[ResumeCache] not domain entity');
            return;
        }

        $user = $entity->getUser();

        if (!$user || !$entity->getId()) {
            error_log('[ResumeCache] missing user or id');
            return;
        }

        if ($oldRole) {
            error_log('[ResumeCache] oldRole=' . $oldRole);
            $this->triggerStandard($user->getId(), $user->getUsername(), $oldRole);
        }

        if (method_exists($entity, 'getRole') && $role = $entity->getRole()) {
            $val = $role instanceof \BackedEnum ? $role->value : $role;
            error_log('[ResumeCache] newRole=' . $val);

            $this->triggerStandard(
                $user->getId(),
                $user->getUsername(),
                $val
            );
        }

        $this->triggerCustom(
            $user->getId(),
            $this->typeMap[$entity::class],
            $entity->getId()
        );
    }

    // REQUIRED to avoid Doctrine fatal
    public function postRemove(): void
    {
        error_log('[ResumeCache] postRemove noop');
    }
}
