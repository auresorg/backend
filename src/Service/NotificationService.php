<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function createNotification(User $user, string $message, ?string $icon = null): Notification
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setMessage($message);
        $notification->setIcon($icon);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }
}
