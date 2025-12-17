<?php

namespace App\EventListener;

use App\Entity\Project;
use App\Entity\Experience;
use App\Entity\Certification;
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

        $this->logger->info("Invalidating Resume Cache for User: {$user->getId()} Role: {$role}");
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

        // Check if ROLE changed to invalidate the old resume too
        if ($this->supports($entity)) {
            $changeSet = $args->getObjectManager()->getUnitOfWork()->getEntityChangeSet($entity);
            if (isset($changeSet['role'])) {
                $oldRoleVal = $changeSet['role'][0];
                $oldRole = $oldRoleVal instanceof \BackedEnum ? $oldRoleVal->value : $oldRoleVal;
                $this->touchCache($conn, $entity->getUser(), $oldRole);
            }
        }
    }

    // FIX: Helper to safely check entity type (ignoring Proxies)
    private function supports(object $entity): bool
    {
        return $entity instanceof Project || 
               $entity instanceof Experience || 
               $entity instanceof Certification;
    }

    private function handleEvent(object $entity, Connection $conn): void
    {
        // FIX: Use instanceof instead of get_class()
        if (!$this->supports($entity)) {
            return;
        }

        // Defensive check just in case
        if (!method_exists($entity, 'getUser') || !method_exists($entity, 'getRole')) {
            return;
        }

        $roleVal = $entity->getRole();
        $role = $roleVal instanceof \BackedEnum ? $roleVal->value : $roleVal;

        $this->touchCache($conn, $entity->getUser(), $role);
    }
}