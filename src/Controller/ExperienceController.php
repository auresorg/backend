<?php

namespace App\Controller;

use App\Entity\Experience;
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

#[Route('/api/experiences')]
#[IsGranted('ROLE_USER')]
final class ExperienceController extends AbstractController
{
    private ResumeCacheInvalidatorHelper $cache;

    public function __construct(ResumeCacheInvalidatorHelper $cache)
    {
        $this->cache = $cache;
    }

    #[Route('', name: 'api_experiences_index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $experiences = $user->getExperiences();

        $data = [];
        foreach ($experiences as $exp) {
            $data[] = [
                'id' => $exp->getId(),
                'title' => $exp->getTitle(),
                'company' => $exp->getCompany(),
                'startDate' => $exp->getStartDate()?->format('Y-m-d'),
                'endDate' => $exp->getEndDate()?->format('Y-m-d'),
                'description' => $exp->getDescription(),
                'role' => $exp->getRole()?->value,
            ];
        }

        return $this->json($data);
    }

    #[Route('', name: 'api_experiences_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        $errors = [];

        if (empty(trim($data['title'] ?? ''))) $errors['title'] = 'Title is required.';
        if (empty(trim($data['company'] ?? ''))) $errors['company'] = 'Company is required.';

        try {
            $startDate = new DateTime($data['startDate'] ?? '');
        } catch (Exception) {
            $errors['startDate'] = 'Invalid start date.';
        }

        $endDate = null;
        if (!empty($data['endDate'])) {
            try { $endDate = new DateTime($data['endDate']); }
            catch (Exception) { $errors['endDate'] = 'Invalid end date.'; }
        }

        $role = $data['role'] ?? null;
        if ($role && !in_array($role, array_column(RoleType::cases(), 'value'), true))
            $errors['role'] = 'Invalid role.';

        if (!empty($errors)) return $this->json(['errors' => $errors], 400);

        $exp = new Experience();
        $exp->setUser($user);
        $exp->setTitle(trim($data['title']));
        $exp->setCompany(trim($data['company']));
        $exp->setStartDate($startDate ?? null);
        $exp->setEndDate($endDate);
        $exp->setDescription($data['description'] ?? null);
        $exp->setRole($role ? RoleType::from($role) : null);

        $em->persist($exp);

        $user->setExperiencesCount($user->getExperiencesCount() + 1);
        $em->persist($user);

        $em->flush();

        $this->cache->invalidateStandard(
            $user->getId(),
            $user->getUsername(),
            $role
        );

        return $this->json(['id' => $exp->getId()], 201);
    }

    #[Route('/{id}', name: 'api_experiences_update', methods: ['PUT', 'PATCH'])]
    public function update(Request $request, int $id, EntityManagerInterface $em): Response
    {
        /** @var User $user */ $user = $this->getUser();
        $exp = $em->getRepository(Experience::class)->find($id);
        if (!$exp || $exp->getUser() !== $user) return $this->json(['error' => 'Not found'], 404);

        $oldRole = $exp->getRole()?->value;

        $data = json_decode($request->getContent(), true);
        $errors = [];

        if (isset($data['title'])) {
            if (!trim($data['title'])) $errors['title']='Title cannot be empty.';
            else $exp->setTitle(trim($data['title']));
        }

        if (isset($data['company'])) {
            if (!trim($data['company'])) $errors['company']='Company cannot be empty.';
            else $exp->setCompany(trim($data['company']));
        }

        if (isset($data['startDate'])) {
            try { $exp->setStartDate(new DateTime($data['startDate'])); }
            catch (Exception) { $errors['startDate']='Invalid start date.'; }
        }

        if (array_key_exists('endDate',$data)) {
            if ($data['endDate']) {
                try { $exp->setEndDate(new DateTime($data['endDate'])); }
                catch (Exception) { $errors['endDate']='Invalid end date.'; }
            } else $exp->setEndDate(null);
        }

        if (isset($data['description'])) $exp->setDescription($data['description'] ?: null);

        if (isset($data['role'])) {
            if (!in_array($data['role'], array_column(RoleType::cases(),'value'), true))
                $errors['role']='Invalid role.';
            else $exp->setRole($data['role'] ? RoleType::from($data['role']) : null);
        }

        if ($errors) return $this->json(['errors'=>$errors],400);

        $em->flush();

        $this->cache->invalidateAfterEntityUpdate(
            $user->getId(),
            $user->getUsername(),
            $oldRole,
            $exp->getRole()?->value,
            'experiences',
            $exp->getId()
        );

        return $this->json(['updated'=>true]);
    }

    #[Route('/{id}', name: 'api_experiences_delete', methods: ['DELETE'])]
    public function delete(int $id, EntityManagerInterface $em): Response
    {
        /** @var User $user */ $user = $this->getUser();
        $exp = $em->getRepository(Experience::class)->find($id);
        if (!$exp || $exp->getUser() !== $user) return $this->json(['error'=>'Not found'],404);

        $em->remove($exp);
        $user->setExperiencesCount($user->getExperiencesCount() - 1);
        $em->persist($user);
        $em->flush();;

        $this->cache->invalidateAfterEntityUpdate(
            $user->getId(),
            $user->getUsername(),
            null,
            $exp->getRole()?->value,
            'experiences',
            $exp->getId()
        );

        return $this->json(null,204);
    }
}
