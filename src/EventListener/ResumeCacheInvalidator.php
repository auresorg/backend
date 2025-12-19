<?php

namespace App\EventListener;

use App\Entity\Project;
use App\Entity\Experience;
use App\Entity\Certification;
use App\Entity\Education;  
use App\Entity\Award;      
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

#[AsDoctrineListener(event: Events::postPersist, priority: 500)]
#[AsDoctrineListener(event: Events::postUpdate, priority: 500)]
#[AsDoctrineListener(event: Events::postRemove, priority: 500)]
class ResumeCacheInvalidator
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    private function touchCache(Connection $conn, $user, ?string $role): void
    {
        if (!$role || !$user) return;

        $this->logger->info("Invalidating Targeted Cache -> User: {$user->getId()} Role: {$role}");

        $sql = "
            UPDATE resumes 
            SET data_updated_at = NOW(), updated_at = NOW()
            WHERE username = :username AND role = :role
        ";

        $conn->executeStatement($sql, [
            'username' => $user->getUsername(),
            'role' => $role
        ]);
    }

    private function touchAllCaches(Connection $conn, $user): void
    {
        if (!$user) return;

        $this->logger->info("Invalidating ALL Caches -> User: {$user->getId()}");

        $sql = "
            UPDATE resumes 
            SET data_updated_at = NOW(), updated_at = NOW()
            WHERE username = :username
        ";

        $conn->executeStatement($sql, [
            'username' => $user->getUsername()
        ]);
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->handleEvent($args->getObject(), $args->getObjectManager()->getConnection());
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->handleEvent($args->getObject(), $args->getObjectManager()->getConnection());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        $conn = $args->getObjectManager()->getConnection();

        $this->handleEvent($entity, $conn);

        if ($this->isTargetedEntity($entity)) {
            $changeSet = $args->getObjectManager()->getUnitOfWork()->getEntityChangeSet($entity);
            if (isset($changeSet['role'])) {
                $oldRoleVal = $changeSet['role'][0];
                $oldRole = $oldRoleVal instanceof \BackedEnum ? $oldRoleVal->value : $oldRoleVal;
                 
                $this->touchCache($conn, $entity->getUser(), $oldRole);
            }
        }
    }

    private function isTargetedEntity(object $entity): bool
    {
        return $entity instanceof Project || 
               $entity instanceof Experience || 
               $entity instanceof Certification ||
               $entity instanceof Award;  
    }

    private function isGlobalEntity(object $entity): bool
    {
        return $entity instanceof Education;  
    }

    private function handleEvent(object $entity, Connection $conn): void
    {
         
        if ($this->isGlobalEntity($entity)) {
            if (method_exists($entity, 'getUser')) {
                $this->touchAllCaches($conn, $entity->getUser());
            }
            return;
        }

        if ($this->isTargetedEntity($entity)) {
            if (!method_exists($entity, 'getUser') || !method_exists($entity, 'getRole')) {
                return;
            }

            $roleVal = $entity->getRole();
            $role = $roleVal instanceof \BackedEnum ? $roleVal->value : $roleVal;

            $this->touchCache($conn, $entity->getUser(), $role);
        }
    }
}