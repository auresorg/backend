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

    /**
     * Updates the timestamp and optionally increments/decrements the count
     */
    private function touchCache(Connection $conn, $user, $role, ?string $countCol = null, int $delta = 0): void
    {
        $roleName = $role instanceof \BackedEnum ? $role->value : $role;

        if (!$roleName || !$user) return;

        $this->logger->info("Invalidating Targeted Cache -> User: {$user->getId()} Role: {$roleName} Delta: {$delta}");

        $sql = "UPDATE resumes SET data_updated_at = NOW(), updated_at = NOW()";

        if ($countCol && $delta !== 0) {
            $op = $delta > 0 ? '+' : '-';
            $amount = abs($delta);
            $sql .= ", {$countCol} = GREATEST({$countCol} {$op} {$amount}, 0)";
        }

        $sql .= " WHERE username = :username AND role = :role";

        $conn->executeStatement($sql, [
            'username' => $user->getUsername(),
            'role' => $roleName
        ]);
    }

    private function touchAllCaches(Connection $conn, $user): void
    {
        if (!$user) return;

        $sql = "UPDATE resumes SET data_updated_at = NOW(), updated_at = NOW() WHERE username = :username";

        $conn->executeStatement($sql, [
            'username' => $user->getUsername()
        ]);
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->handleEvent($args->getObject(), $args->getObjectManager()->getConnection(), 1);
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->handleEvent($args->getObject(), $args->getObjectManager()->getConnection(), -1);
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        $conn = $args->getObjectManager()->getConnection();

        // 1. Handle Global Entities (Education) - No counts, just invalidation
        if ($this->isGlobalEntity($entity)) {
            if (method_exists($entity, 'getUser')) {
                $this->touchAllCaches($conn, $entity->getUser());
            }
            return;
        }

        // 2. Handle Targeted Entities (Projects, Awards, etc.)
        if ($this->isTargetedEntity($entity)) {
            $uow = $args->getObjectManager()->getUnitOfWork();
            $changeSet = $uow->getEntityChangeSet($entity);
            $countCol = $this->getCountColumn($entity);
            $user = $entity->getUser();

            // Check if the ROLE specifically was changed
            if (isset($changeSet['role'])) {
                $oldRole = $changeSet['role'][0];
                $newRole = $changeSet['role'][1];

                // DECREMENT count for the OLD role
                $this->touchCache($conn, $user, $oldRole, $countCol, -1);

                // INCREMENT count for the NEW role
                $this->touchCache($conn, $user, $newRole, $countCol, 1);
            } else {
                // Role did NOT change (user just edited title/description)
                // Just update the timestamp, do not change count (delta 0)
                $this->touchCache($conn, $user, $entity->getRole(), null, 0);
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

    private function getCountColumn(object $entity): ?string
    {
        return match(true) {
            $entity instanceof Project => 'projects',
            $entity instanceof Experience => 'experience',
            $entity instanceof Certification => 'certificates',
            $entity instanceof Award => 'awards',
            default => null,
        };
    }

    private function handleEvent(object $entity, Connection $conn, int $delta = 0): void
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

            $countCol = $this->getCountColumn($entity);
            $this->touchCache($conn, $entity->getUser(), $entity->getRole(), $countCol, $delta);
        }
    }
}