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

#[Route('/api/certifications')]
#[IsGranted('ROLE_USER')]
final class CertificationController extends AbstractController
{
    private ResumeCacheInvalidatorHelper $cache;

    public function __construct(ResumeCacheInvalidatorHelper $cache)
    {
        $this->cache = $cache;
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
                'url' => $certification->getUrl(),
                'role' => $certification->getRole()?->value,
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
            'url' => $certification->getUrl(),
            'role' => $certification->getRole()?->value,
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

        $role = $data['role'] ?? null;
        if ($role && !in_array($role, array_column(RoleType::cases(), 'value'), true)) {
            $errors['role'] = 'Role must be one of: frontend, backend, fullstack, devops.';
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
        $certification->setUrl(isset($data['url']) ? trim($data['url']) : null);
        $certification->setRole($role ? RoleType::from($role) : null);

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
            'url' => $certification->getUrl(),
            'role' => $certification->getRole()?->value,
            'completedOn' => $certification->getCompletedOn()?->format('Y-m-d'),
        ];

        $this->cache->invalidateStandard(
            $user->getId(),
            $user->getUsername(),
            $role
        );

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

        $oldRole = $certification->getRole()?->value;

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

        $role = $data['role'] ?? null;
        if (!in_array($role, array_column(RoleType::cases(), 'value'), true)) {
            $errors['role'] = 'Role must be one of: frontend, backend, fullstack, devops.';
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
            'url' => $certification->getUrl(),
            'role' => $certification->getRole()?->value,
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

        $entityManager->remove($certification);

        $user->decrementCertCount();
        $entityManager->persist($user);

        $entityManager->flush();

        $this->cache->invalidateAfterEntityUpdate(
            $user->getId(),
            $user->getUsername(),
            null,
            $certification->getRole()?->value,
            'certifications',
            $certification->getId()
        );

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
