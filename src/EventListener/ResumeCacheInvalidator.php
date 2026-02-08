<?php

namespace App\EventListener;

use App\Entity\{Project, Experience, Certification, Award, Education, User};
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\{PostPersistEventArgs, PostUpdateEventArgs, PreRemoveEventArgs};
use Doctrine\ORM\Events;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\HttpClient\HttpClientInterface;

//TODO: MUST INCLUDE connection: 'default' WHEN PUSHING TO PRODUCTION, OTHERWISE IT WILL NOT WORK IN PRODUCTION ENVIRONMENT
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
    ) {}

    /* ----------------- HTTP ----------------- */

    private function fire(array $payload): void
    {
        $payload['_call_id'] = uniqid('php_', true);

        $ch = curl_init($this->serverlessUrl);

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->serverlessUrl,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),

            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_NOBODY => false,

            CURLOPT_CONNECTTIMEOUT_MS => 200,
            CURLOPT_TIMEOUT_MS => 200,

            CURLOPT_FORBID_REUSE => true,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_NOSIGNAL => true,
        ]);

        $ok = curl_exec($ch);

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

        if (!$column) {
            return;
        }

        $sql = "SELECT slug FROM cusres
                WHERE user_id = :uid
                AND $column::jsonb @> :id::jsonb";

        $rows = $this->db->fetchAllAssociative($sql, [
            'uid' => $userId,
            'id' => json_encode([$entityId]),
        ]);

        foreach ($rows as $row) {

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

        $rows = $this->db->fetchAllAssociative($sql, ['id' => $user->getId()]);

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

        if ($entity instanceof User) {

            foreach ($uow->getScheduledEntityUpdates() as $updated) {
                if (isset($this->typeMap[$updated::class])) {
                    return;
                }
            }

            $meaningful = array_diff(array_keys($changeSet), $this->ignoredUserFields);

            if (!$meaningful) {
                return;
            }

            $this->triggerContentRoles($entity);
            return;
        }

        if ($entity instanceof Education) {
            $this->triggerContentRoles($entity->getUser());
            return;
        }

        $oldRole = $changeSet['role'][0] ?? null;
        $this->handleDomainEntity($entity, $oldRole);
    }

    private function handleDomainEntity(object $entity, ?string $oldRole): void
    {

        if (!isset($this->typeMap[$entity::class])) {
            return;
        }

        $user = $entity->getUser();

        if (!$user || !$entity->getId()) {
            return;
        }

        if ($oldRole) {
            $this->triggerStandard($user->getId(), $user->getUsername(), $oldRole);
        }

        if (method_exists($entity, 'getRole') && $role = $entity->getRole()) {
            $val = $role instanceof \BackedEnum ? $role->value : $role;

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
    }
}
