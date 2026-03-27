<?php

namespace App\Controller;

use App\Entity\Certification;
use App\Entity\RoleType;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\ResumeCacheInvalidatorHelper;
use App\Service\NotificationService;

#[Route('/api/certifications')]
#[IsGranted('ROLE_USER')]
final class CertificationController extends AbstractController
{
    private ResumeCacheInvalidatorHelper $cache;
    private NotificationService $notificationService;

    public function __construct(ResumeCacheInvalidatorHelper $cache, NotificationService $notificationService)
    {
        $this->cache = $cache;
        $this->notificationService = $notificationService;
    }

    #[Route('', name: 'api_certifications_index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $certifications = $user->getCertifications();

        $certificationsData = [];
        foreach ($certifications as $certification) {
            $certificationsData[] = [
                'id' => $certification->getId(),
                'title' => $certification->getTitle(),
                'platform' => $certification->getPlatform(),
                'description' => $certification->getDescription(),
                'role' => $certification->getRole(),
                'url' => $certification->getUrl(),
                'completedOn' => $certification->getCompletedOn()?->format('Y-m-d'),
            ];
        }

        return $this->json($certificationsData, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'api_certifications_show', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $certification = $entityManager->getRepository(Certification::class)->find($id);

        if (!$certification || $certification->getUser() !== $user) {
            return $this->json(['error' => 'Certification not found'], Response::HTTP_NOT_FOUND);
        }

        $certificationData = [
            'id' => $certification->getId(),
            'title' => $certification->getTitle(),
            'platform' => $certification->getPlatform(),
            'description' => $certification->getDescription(),
            'role' => $certification->getRole(),
            'url' => $certification->getUrl(),
            'completedOn' => $certification->getCompletedOn()?->format('Y-m-d'),
        ];

        return $this->json($certificationData, Response::HTTP_OK);
    }

    #[Route('', name: 'api_certifications_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        $errors = [];

        // Validation
        if (!isset($data['title']) || empty(trim($data['title']))) {
            $errors['title'] = 'Title is required.';
        }

        if (!isset($data['platform']) || empty(trim($data['platform']))) {
            $errors['platform'] = 'Platform is required.';
        }

        if (!empty($data['url']) && !filter_var($data['url'], FILTER_VALIDATE_URL)) {
            $errors['url'] = 'URL must be a valid URL.';
        }

        $role = $data['role'] ?? [];
        if (!is_array($role)) {
            $errors['role'] = 'Role must be an array.';
        } elseif (empty($role)) {
            $errors['role'] = 'At least one role must be specified.';
        } else {
            foreach ($role as $r) {
                if (!in_array($r, array_column(RoleType::cases(), 'value'), true)) {
                    $errors['role'] = 'Invalid role(s) provided.';
                    break;
                }
            }
        }

        try {
            if (!empty($data['completedOn'])) {
                $completedOn = new \DateTime($data['completedOn']);
                if ($completedOn > new \DateTime()) {
                    $errors['completedOn'] = 'Completion date cannot be in the future.';
                }
            }
        } catch (\Exception $e) {
            $errors['completedOn'] = 'Invalid completion date.';
        }

        if (!empty($errors)) {
            return $this->json(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        $certification = new Certification();
        $certification->setUser($user);
        $certification->setTitle(trim($data['title']));
        $certification->setPlatform(trim($data['platform']));
        $certification->setDescription(isset($data['description']) ? trim($data['description']) : null);
        $certification->setRole($role);

        if (!empty($data['completedOn'])) {
            $certification->setCompletedOn(new \DateTime($data['completedOn']));
        }

        $entityManager->persist($certification);

        $user->incrementCertCount();
        $entityManager->persist($user);

        $entityManager->flush();

        $certificationData = [
            'id' => $certification->getId(),
            'title' => $certification->getTitle(),
            'platform' => $certification->getPlatform(),
            'description' => $certification->getDescription(),
            'role' => $certification->getRole(),
            'url' => $certification->getUrl(),
            'completedOn' => $certification->getCompletedOn()?->format('Y-m-d'),
        ];

        foreach ($role as $r) {
            $this->cache->invalidateStandard(
                $user->getId(),
                $user->getUsername(),
                $r
            );
        }

        $totalItems = $user->getProjectsCount() + $user->getExperienceCount() + $user->getCertCount() + $user->getAwardsCount();
        if ($totalItems === 2) {
             $this->notificationService->createNotification($user, "You now have multiple items! Checkout the custom resumes section to build a tailored resume.", "info");
        }

        return $this->json($certificationData, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_certifications_update', methods: ['PUT', 'PATCH'])]
    public function update(Request $request, int $id, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $certification = $entityManager->getRepository(Certification::class)->find($id);

        if (!$certification || $certification->getUser() !== $user) {
            return $this->json(['error' => 'Certification not found'], Response::HTTP_NOT_FOUND);
        }

        $oldRole = $certification->getRole();

        $data = json_decode($request->getContent(), true);
        $errors = [];

        if (isset($data['title'])) {
            if (empty(trim($data['title']))) {
                $errors['title'] = 'Title cannot be empty.';
            } else {
                $certification->setTitle(trim($data['title']));
            }
        }

        if (isset($data['platform'])) {
            if (empty(trim($data['platform']))) {
                $errors['platform'] = 'Platform cannot be empty.';
            } else {
                $certification->setPlatform(trim($data['platform']));
            }
        }

        if (isset($data['url'])) {
            if (!empty($data['url']) && !filter_var($data['url'], FILTER_VALIDATE_URL)) {
                $errors['url'] = 'URL must be a valid URL.';
            } else {
                $certification->setUrl(!empty($data['url']) ? trim($data['url']) : null);
            }
        }

        if (isset($data['description'])) {
            $certification->setDescription(!empty($data['description']) ? trim($data['description']) : null);
        }

        if (isset($data['role'])) {
            if (!is_array($data['role'])) {
                $errors['role'] = 'Role must be an array.';
            } elseif (empty($data['role'])) {
                $errors['role'] = 'At least one role must be specified.';
            } else {
                foreach ($data['role'] as $r) {
                    if (!in_array($r, array_column(RoleType::cases(), 'value'), true)) {
                        $errors['role'] = 'Invalid role(s) provided.';
                        break;
                    }
                }
                if (!isset($errors['role'])) {
                    $certification->setRole($data['role']);
                    $role = $data['role']; // used for invalidateAfterEntityUpdate below
                }
            }
        } else {
            $role = $oldRole; 
        }

        try {
            if (isset($data['completedOn'])) {
                if (!empty($data['completedOn'])) {
                    $completedOn = new \DateTime($data['completedOn']);
                    if ($completedOn > new \DateTime()) {
                        $errors['completedOn'] = 'Completion date cannot be in the future.';
                    } else {
                        $certification->setCompletedOn($completedOn);
                    }
                } else {
                    $certification->setCompletedOn(null);
                }
            }
        } catch (\Exception $e) {
            $errors['completedOn'] = 'Invalid completion date.';
        }

        if (!empty($errors)) {
            return $this->json(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        $entityManager->flush();

        $certificationData = [
            'id' => $certification->getId(),
            'title' => $certification->getTitle(),
            'platform' => $certification->getPlatform(),
            'description' => $certification->getDescription(),
            'role' => $certification->getRole(),
            'url' => $certification->getUrl(),
            'completedOn' => $certification->getCompletedOn()?->format('Y-m-d'),
        ];

        $this->cache->invalidateAfterEntityUpdate(
            $user->getId(),
            $user->getUsername(),
            $oldRole,
            $role,
            'certifications',
            $certification->getId()
        );

        return $this->json($certificationData, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'api_certifications_delete', methods: ['DELETE'])]
    public function delete(int $id, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $certification = $entityManager->getRepository(Certification::class)->find($id);

        if (!$certification || $certification->getUser() !== $user) {
            return $this->json(['error' => 'Certification not found'], Response::HTTP_NOT_FOUND);
        }

        $id = $certification->getId();

        $entityManager->remove($certification);

        $user->decrementCertCount();
        $entityManager->persist($user);

        $entityManager->flush();

        $this->cache->invalidateAfterEntityUpdate(
            $user->getId(),
            $user->getUsername(),
            [],
            $certification->getRole(),
            'certifications',
            $id
        );

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
