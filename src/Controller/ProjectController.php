<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\RoleType;
use App\Entity\User;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\ResumeCacheInvalidatorHelper;

#[Route('/api/projects')]
#[IsGranted('ROLE_USER')]
final class ProjectController extends AbstractController
{
    private ResumeCacheInvalidatorHelper $cache;

    public function __construct(ResumeCacheInvalidatorHelper $cache)
    {
        $this->cache = $cache;
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
            $url = "https://" . $url;
        }
        return $url;
    }

    #[Route('', name: 'api_project_index', methods: ['GET'])]
    public function index(ProjectRepository $projectRepository): Response
    {
        $user = $this->getUser();
        $projects = $projectRepository->findBy(['user' => $user]);

        $projectsArray = array_map(function($project) {
            return [
                'id' => $project->getId(),
                'name' => $project->getName(),
                'repo' => $project->getRepo(),
                'url' => $project->getUrl(), // NEW
                'tech' => $project->getTech(),
                'description' => $project->getDescription(),
                'role' => $project->getRole()->value,
                'startDate' => $project->getStartDate()->format('Y-m-d'),
                'endDate' => $project->getEndDate()?->format('Y-m-d')
            ];
        }, $projects);

        return $this->json($projectsArray, Response::HTTP_OK);
    }

    #[Route('', name: 'api_project_new', methods: ['POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $data = json_decode($request->getContent(), true);

        /** @var User $user */
        $user = $this->getUser();

        if (!$data || !is_array($data)) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $errors = [];

        // Validate required fields
        $name = trim($data['name'] ?? '');
        if ($name === '' || strlen($name) > 255) {
            $errors['name'] = 'Name is required and must be at most 255 characters.';
        }

        $repo = trim($data['repo'] ?? '');
        if ($repo === '' || strlen($repo) > 140) {
            $errors['repo'] = 'Repo URL is required and must be at most 140 characters.';
        }

        $description = trim($data['description'] ?? null);
        if (strlen($description) < 100 ) {
            $errors['description'] = 'Description must be at least 100 characters.';
        }

        $tech = $data['tech'] ?? [];
        if (!is_array($tech)) {
            $errors['tech'] = 'Tech must be an array.';
        }
        if (empty($tech)) {
            $errors['tech'] = 'At least one technology must be specified.';
        }

        $role = $data['role'] ?? null;
        if ($role && !in_array($role, array_column(RoleType::cases(), 'value'), true)) {
            $errors['role'] = 'Role must be one of: frontend, backend, fullstack, devops.';
        }

        // Validate dates
        try {
            $from = new \DateTime($data['startDate'] ?? '');
        } catch (\Exception $e) {
            $errors['startDate'] = 'Invalid start date.';
        }

        $to = null;
        if (!empty($data['endDate'])) {
            try {
                $to = new \DateTime($data['endDate']);
            } catch (\Exception $e) {
                $errors['to'] = 'Invalid end date.';
            }
        }

        if (!empty($errors)) {
            return $this->json(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        $project = new Project();
        $project->setUser($this->getUser());
        $project->setName($name);
        $project->setRepo($repo);
        $project->setDescription($description);
        $project->setTech($tech);
        $project->setRole(RoleType::from($role));
        $project->setStartDate($from);
        $project->setEndDate($to);

        $entityManager->persist($project);

        $user->incrementProjectsCount();
        foreach ($project->getTech() as $tech) {
            $user->incrementSkill($tech);
        }
        $entityManager->persist($user);

        $entityManager->flush();

        $response = $this->json([
            'id' => $project->getId(),
            'name' => $project->getName(),
            'repo' => $project->getRepo(),
            'url' => $project->getUrl(),
            'tech' => $project->getTech(),
            'description' => $project->getDescription(),
            'role' => $project->getRole()->value,
            'startDate' => $project->getStartDate()->format('Y-m-d'),
            'endDate' => $project->getEndDate()?->format('Y-m-d')
        ], Response::HTTP_CREATED);

        // if (function_exists('fastcgi_finish_request')) {
        //     fastcgi_finish_request();
        // }

        $this->cache->invalidateStandard(
            $user->getId(),
            $user->getUsername(),
            $role       
        );

        return $response;
    }

    #[Route('/{id}', name: 'api_project_edit', methods: ['PUT', 'PATCH'])]
    public function edit(Request $request, Project $project, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $errors = [];

        if ($project->getUser() !== $user) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        $oldRole = $project->getRole()?->value;

        if (isset($data['name'])) {
            $project->setName($data['name']);
        }

        if (isset($data['description'])) {
            if (strlen($data['description']) < 100 ) {
                $errors['description'] = 'Description must be at least 100 characters.';
            } else {
                $project->setDescription($data['description']);
            }
        }

        if (isset($data['repo'])) {
            if (strlen($data['repo']) > 140) {
                $errors['repo'] = 'Repo URL must be at most 140 characters.';
            } else {
                $project->setRepo($data['repo']);
            }
        }

        if (isset($data['url'])) {
            $normalizedUrl = $this->normalizeUrl($data['url']);
            $project->setUrl($normalizedUrl);
        } else {
            $project->setUrl(null);
        }

        //check tech. if removed any tech, decrement user skill. if added any tech, increment user skill.
        if (isset($data['tech']) && is_array($data['tech'])) {
            $oldTech = $project->getTech();
            $newTech = $data['tech'];
            $removedTech = array_diff($oldTech, $newTech);
            $addedTech = array_diff($newTech, $oldTech);
            foreach ($removedTech as $tech) {
                $user->decrementSkill($tech);
            }
            foreach ($addedTech as $tech) {
                $user->incrementSkill($tech);
            }
            $project->setTech($newTech);
        }

        if (isset($data['role'])) {
            if (!in_array($data['role'], array_column(RoleType::cases(), 'value'), true)) {
                $errors['role'] = 'Role must be one of: frontend, backend, fullstack, devops.';
            } else {
                $project->setRole(RoleType::from($data['role']));
            }
        }

        if (isset($data['startDate'])) {
            try {
                $from = new \DateTime($data['startDate']);
                $project->setStartDate($from);
            } catch (\Exception $e) {
                $errors['startDate'] = 'Invalid from date.';
            }
        }

        if (array_key_exists('endDate', $data)) {
            if ($data['endDate'] === null || $data['endDate'] === '') {
                $project->setEndDate(null);
            } else {
                try {
                    $to = new \DateTime($data['endDate']);
                    $project->setEndDate($to);
                } catch (\Exception $e) {
                    $errors ['endDate'] = 'Invalid to date.';
                }
            }
        }

        if (!empty($errors)) {
            return $this->json(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        $entityManager->flush();

        $response = $this->json(null, Response::HTTP_OK);

        // if (function_exists('fastcgi_finish_request')) {
        //     fastcgi_finish_request();
        // }

        $this->cache->invalidateAfterEntityUpdate(
            $user->getId(),
            $user->getUsername(),
            $oldRole,
            $project->getRole()?->value,
            'projects',
            $project->getId()
        );

        return $response;
    }

    #[Route('/{id}', name: 'api_project_delete', methods: ['DELETE'])]
    public function delete(Project $project, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($project->getUser() !== $user) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $entityManager->remove($project);

        $user->decrementProjectsCount();
        foreach ($project->getTech() as $tech) {
            $user->decrementSkill($tech);
        }

        $entityManager->flush();

        $response = $this->json(null, Response::HTTP_NO_CONTENT);

        // if (function_exists('fastcgi_finish_request')) {
        //     fastcgi_finish_request();
        // }

        $this->cache->invalidateAfterEntityUpdate(
            $user->getId(),
            $user->getUsername(),
            null,
            $project->getRole()?->value,
            'projects',
            $project->getId()
        );

        return $response;
    }
}
