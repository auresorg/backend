<?php

namespace App\EventListener;

use App\Entity\{Project, Experience, Certification, Award, Education, User};
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\{PostPersistEventArgs, PostUpdateEventArgs, PreRemoveEventArgs};
use Doctrine\ORM\Events;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsDoctrineListener(event: Events::postPersist, priority: 500)]
#[AsDoctrineListener(event: Events::postUpdate, priority: 500)]
#[AsDoctrineListener(event: Events::preRemove, priority: 500)]
class ResumeCacheInvalidator
{
    private array $typeMap = [
        Project::class => 'project',
        Experience::class => 'experience',
        Certification::class => 'certification',
        Award::class => 'award',
    ];

    /** User fields that never trigger resumes */
    private array $ignoredUserFields = [
        'skills',
        'projectsCount',
        'certCount',
        'awardsCount',
        'experienceCount',
        'projects',        // 🔑 collection side-effect
        'experiences',
        'certifications',
        'awards',
    ];

    public function __construct(
        private HttpClientInterface $http,
        private Connection $db,
        private string $serverlessUrl
    ) {
    }

    /* ----------------- HTTP ----------------- */

    private function fire(array $payload): void
    {
        $payload['_call_id'] = uniqid('php_', true);

        $ch = curl_init($this->serverlessUrl);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_CONNECTTIMEOUT_MS => 200,
            CURLOPT_TIMEOUT_MS => 200,
            CURLOPT_NOSIGNAL => true,
        ]);

        curl_exec($ch);
        curl_close($ch);
    }


    private function triggerStandard(int $userId, string $username, string $role): void
    {
        $this->fire([
            'type' => 'standard',
            'userId' => $userId,
            'username' => $username,
            'role' => $role,
        ]);
    }

    private function triggerCustom(int $userId, string $type, int $entityId): void
    {
        $column = match ($type) {
            'project' => 'projects',
            'experience' => 'experiences',
            'certification' => 'certifications',
            'award' => 'awards',
            default => null
        };
        if (!$column)
            return;

        $sql = "SELECT slug FROM cusres
                WHERE user_id = :uid
                AND $column::jsonb @> :id::jsonb";

        foreach ($this->db->fetchAllAssociative($sql, [
            'uid' => $userId,
            'id' => json_encode([$entityId]),
        ]) as $row) {
            $this->fire([
                'type' => 'custom',
                'userId' => $userId,
                'slug' => $row['slug'],
            ]);
        }
    }

    private function triggerContentRoles(User $user): void
    {
        $sql = "
            SELECT role FROM resumes
            WHERE user_id = :id
            AND (projects + certificates + awards + experience) > 0
        ";

        foreach ($this->db->fetchAllAssociative($sql, ['id' => $user->getId()]) as $row) {
            $this->triggerStandard($user->getId(), $user->getUsername(), $row['role']);
        }
    }

    /* ----------------- EVENTS ----------------- */

    public function postPersist(PostPersistEventArgs $e): void
    {
        $this->handleDomainEntity($e->getObject(), null);
    }

    public function preRemove(PreRemoveEventArgs $e): void
    {
        $this->handleDomainEntity($e->getObject(), null);
    }

    public function postUpdate(PostUpdateEventArgs $e): void
    {
        $entity = $e->getObject();
        $uow = $e->getObjectManager()->getUnitOfWork();
        $changeSet = $uow->getEntityChangeSet($entity);

        /* ---- USER ---- */
        if ($entity instanceof User) {

            // 🔥 If ANY domain entity is also updated, ignore User completely
            foreach ($uow->getScheduledEntityUpdates() as $updated) {
                if (isset($this->typeMap[$updated::class])) {
                    return;
                }
            }

            $changed = array_keys($changeSet);
            $meaningful = array_diff($changed, $this->ignoredUserFields);

            if (empty($meaningful))
                return;

            $this->triggerContentRoles($entity);
            return;
        }

        /* ---- EDUCATION ---- */
        if ($entity instanceof Education) {
            $this->triggerContentRoles($entity->getUser());
            return;
        }

        /* ---- DOMAIN ENTITIES ---- */
        $oldRole = $changeSet['role'][0] ?? null;
        $this->handleDomainEntity($entity, $oldRole);
    }

    private function handleDomainEntity(object $entity, ?string $oldRole): void
    {
        if (!isset($this->typeMap[$entity::class]))
            return;

        $user = $entity->getUser();
        if (!$user || !$entity->getId())
            return;

        if ($oldRole) {
            $this->triggerStandard($user->getId(), $user->getUsername(), $oldRole);
        }

        if (method_exists($entity, 'getRole') && $role = $entity->getRole()) {
            $this->triggerStandard(
                $user->getId(),
                $user->getUsername(),
                $role instanceof \BackedEnum ? $role->value : $role
            );
        }

        $this->triggerCustom(
            $user->getId(),
            $this->typeMap[$entity::class],
            $entity->getId()
        );
    }
}
