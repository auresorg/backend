<?php

namespace App\Controller;

use App\Entity\Award;
use App\Entity\RoleType;
use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\ResumeCacheInvalidatorHelper;

#[Route('/api/awards')]
#[IsGranted('ROLE_USER')]
final class AwardController extends AbstractController
{
    private const AWARD_TYPES = ['first', 'second', 'third', 'fourth', 'participation'];
    private ResumeCacheInvalidatorHelper $cache;

    public function __construct(ResumeCacheInvalidatorHelper $cache)
    {
        $this->cache = $cache;
    }

    #[Route('', name: 'api_awards_index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $awards = $user->getAwards();

        $awardsData = [];
        foreach ($awards as $award) {
            $awardsData[] = [
                'id' => $award->getId(),
                'title' => $award->getTitle(),
                'issuer' => $award->getIssuer(),
                'type' => $award->getType(),
                'description' => $award->getDescription(),
                'date' => $award->getDate()?->format('Y-m-d'),
                'role' => $award->getRole()?->value,
            ];
        }

        return $this->json($awardsData, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'api_awards_show', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $award = $entityManager->getRepository(Award::class)->find($id);

        if (!$award || $award->getUser() !== $user) {
            return $this->json(['error' => 'Award not found'], Response::HTTP_NOT_FOUND);
        }

        $awardData = [
            'id' => $award->getId(),
            'title' => $award->getTitle(),
            'issuer' => $award->getIssuer(),
            'type' => $award->getType(),
            'description' => $award->getDescription(),
            'date' => $award->getDate()?->format('Y-m-d'),
            'role' => $award->getRole()?->value,
        ];

        return $this->json($awardData, Response::HTTP_OK);
    }

    #[Route('', name: 'api_awards_create', methods: ['POST'])]
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

        if (!isset($data['issuer']) || empty(trim($data['issuer']))) {
            $errors['issuer'] = 'Issuer is required.';
        }

        if (!isset($data['type']) || empty(trim($data['type']))) {
            $errors['type'] = 'Type is required.';
        } elseif (!in_array($data['type'], self::AWARD_TYPES, true)) {
            $errors['type'] = 'Type must be one of: ' . implode(', ', self::AWARD_TYPES);
        }

        $role = $data['role'] ?? null;
        if ($role && !in_array($role, array_column(RoleType::cases(), 'value'), true)) {
            $errors['role'] = 'Role must be one of: frontend, backend, fullstack, devops.';
        }

        $date = null;
        try {
            if (!empty($data['date'])) {
                $date = new DateTime($data['date']);
                if ($date > new DateTime()) {
                    $errors['date'] = 'Date cannot be in the future.';
                }
            }
        } catch (Exception) {
            $errors['date'] = 'Invalid date.';
        }

        if (!empty($errors)) {
            return $this->json(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        $award = new Award();
        $award->setUser($user);
        $award->setTitle(trim($data['title']));
        $award->setIssuer(trim($data['issuer']));
        $award->setType(trim($data['type']));
        $award->setDescription(isset($data['description']) ? trim($data['description']) : null);
        $award->setRole($role ? RoleType::from($role) : null);
        $award->setDate($date ?? null);

        $entityManager->persist($award);

        $user->setAwardsCount($user->getAwardsCount() + 1);
        $entityManager->persist($user);

        $entityManager->flush();

        $awardData = [
            'id' => $award->getId(),
            'title' => $award->getTitle(),
            'issuer' => $award->getIssuer(),
            'type' => $award->getType(),
            'description' => $award->getDescription(),
            'date' => $award->getDate()?->format('Y-m-d'),
            'role' => $award->getRole()?->value,
        ];

        // if (function_exists('fastcgi_finish_request')) {
        //     fastcgi_finish_request();
        // }

        $this->cache->invalidateStandard(
            $user->getId(),
            $user->getUsername(),
            $role
        );

        return $this->json($awardData, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_awards_update', methods: ['PUT', 'PATCH'])]
    public function update(Request $request, int $id, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $award = $entityManager->getRepository(Award::class)->find($id);

        if (!$award || $award->getUser() !== $user) {
            return $this->json(['error' => 'Award not found'], Response::HTTP_NOT_FOUND);
        }

        $oldRole = $award->getRole()?->value;

        $data = json_decode($request->getContent(), true);
        $errors = [];

        if (isset($data['title'])) {
            if (empty(trim($data['title']))) {
                $errors['title'] = 'Title cannot be empty.';
            } else {
                $award->setTitle(trim($data['title']));
            }
        }

        if (isset($data['issuer'])) {
            if (empty(trim($data['issuer']))) {
                $errors['issuer'] = 'Issuer cannot be empty.';
            } else {
                $award->setIssuer(trim($data['issuer']));
            }
        }

        if (isset($data['type'])) {
            if (empty(trim($data['type']))) {
                $errors['type'] = 'Type cannot be empty.';
            } elseif (!in_array($data['type'], self::AWARD_TYPES, true)) {
                $errors['type'] = 'Type must be one of: ' . implode(', ', self::AWARD_TYPES);
            } else {
                $award->setType(trim($data['type']));
            }
        }

        if (isset($data['description'])) {
            $award->setDescription(!empty($data['description']) ? trim($data['description']) : null);
        }

        $role = $data['role'] ?? null;
        if (isset($data['role']) && !in_array($role, array_column(RoleType::cases(), 'value'), true)) {
            $errors['role'] = 'Role must be one of: frontend, backend, fullstack, devops.';
        } elseif (isset($data['role'])) {
            $award->setRole($role ? RoleType::from($role) : null);
        }

        try {
            if (isset($data['date'])) {
                if (!empty($data['date'])) {
                    $date = new DateTime($data['date']);
                    if ($date > new DateTime()) {
                        $errors['date'] = 'Date cannot be in the future.';
                    } else {
                        $award->setDate($date);
                    }
                } else {
                    $award->setDate(null);
                }
            }
        } catch (Exception) {
            $errors['date'] = 'Invalid date.';
        }

        if (!empty($errors)) {
            return $this->json(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        $entityManager->flush();

        $awardData = [
            'id' => $award->getId(),
            'title' => $award->getTitle(),
            'issuer' => $award->getIssuer(),
            'type' => $award->getType(),
            'description' => $award->getDescription(),
            'date' => $award->getDate()?->format('Y-m-d'),
            'role' => $award->getRole()?->value,
        ];

        $this->cache->invalidateAfterEntityUpdate(
            $user->getId(),
            $user->getUsername(),
            $oldRole,
            $role,
            'awards',
            $award->getId()
        );

        return $this->json($awardData, Response::HTTP_OK);;
    }

    #[Route('/{id}', name: 'api_awards_delete', methods: ['DELETE'])]
    public function delete(int $id, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $award = $entityManager->getRepository(Award::class)->find($id);

        if (!$award || $award->getUser() !== $user) {
            return $this->json(['error' => 'Award not found'], Response::HTTP_NOT_FOUND);
        }

        $entityManager->remove($award);

        $user->setAwardsCount($user->getAwardsCount() - 1);
        $entityManager->persist($user);

        $entityManager->flush();


        $this->cache->invalidateAfterEntityUpdate(
            $user->getId(),
            $user->getUsername(),
            null,
            $award->getRole()?->value,
            'awards',
            $award->getId()
        );

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
