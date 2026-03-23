<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/notifications')]
class NotificationController extends AbstractController
{
    #[Route('', name: 'api_notifications_get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getNotifications(NotificationRepository $notificationRepository): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $notifications = $notificationRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        $data = array_map(function (Notification $notification) {
            return [
                'id' => $notification->getId(),
                'message' => $notification->getMessage(),
                'icon' => $notification->getIcon(),
                'createdAt' => $notification->getCreatedAt()->format('c'),
            ];
        }, $notifications);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'api_notifications_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteNotification(
        int $id,
        NotificationRepository $notificationRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $notification = $notificationRepository->find($id);

        if (!$notification) {
            return $this->json(['error' => 'Notification not found'], 404);
        }

        if ($notification->getUser() !== $user) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        $entityManager->remove($notification);
        $entityManager->flush();

        return $this->json(['message' => 'Notification deleted successfully']);
    }

    // Temporary endpoint for testing
    #[Route('/test/create', name: 'api_notifications_test_create', methods: ['POST', 'GET'])]
    #[IsGranted('ROLE_USER')]
    public function createTestNotification(\App\Service\NotificationService $notificationService): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $notification = $notificationService->createNotification(
            $user,
            "Welcome to the new dashboard! You can now receive notifications.",
            "bell" // lucide icon name
        );

        return $this->json([
            'id' => $notification->getId(),
            'message' => $notification->getMessage(),
            'icon' => $notification->getIcon(),
        ]);
    }
}
