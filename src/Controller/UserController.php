<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/user')]
class UserController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    #[Route('', methods: ['GET'])]
    public function getCurrentUser(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new Response(null, 401);
        }

        return $this->json([
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'avatarUrl' => $user->getAvatarUrl(),
            'plan' => $user->getPlan(),
            'linkedin' => $user->getLinkedin(),
            'leetcode' => $user->getLeetcode(),
            'portfolio' => $user->getPortfolio(),
            'skills' => $user->getSkills(),
            'projectsCount' => $user->getProjectsCount(),
            'certCount' => $user->getCertCount(),
            'awardsCount' => $user->getAwardsCount(),
            'experienceCount' => $user->getExperienceCount()
        ]);
    }

    #[Route('', methods: ['PUT'])]
    public function updateCurrentUser(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['firstName'])) $user->setFirstName($data['firstName']);
        if (isset($data['lastName'])) $user->setLastName($data['lastName']);
        if (isset($data['linkedin'])) $user->setLinkedin($data['linkedin']);
        if (isset($data['leetcode'])) $user->setLeetcode($data['leetcode']);
        if (isset($data['portfolio'])) $user->setPortfolio($data['portfolio']);

        $this->em->flush();

        return $this->json([
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'avatarUrl' => $user->getAvatarUrl(),
            'plan' => $user->getPlan(),
            'linkedin' => $user->getLinkedin(),
            'leetcode' => $user->getLeetcode(),
            'skills' => $user->getSkills(),
            'projectsCount' => $user->getProjectsCount(),
            'certCount' => $user->getCertCount(),
            'awardsCount' => $user->getAwardsCount(),
            'experienceCount' => $user->getExperienceCount(),
        ]);
    }

    #[Route('/privacy', methods: ['GET'])]
    public function getPrivacySettings(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        return $this->json([
            'showEmail' => $user->isShowEmail(),
            'showProjects' => $user->isShowProjects(),
            'showExperience' => $user->isShowExperience(),
            'showCertifications' => $user->isShowCertifications(),
            'showEducation' => $user->isShowEducation(),
            'showAwards' => $user->isShowAwards(),
        ]);
    }

    #[Route('/privacy', methods: ['PUT'])]
    public function updatePrivacySettings(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['showEmail'])) $user->setShowEmail((bool)$data['showEmail']);
        if (isset($data['showProjects'])) $user->setShowProjects((bool)$data['showProjects']);
        if (isset($data['showExperience'])) $user->setShowExperience((bool)$data['showExperience']);
        if (isset($data['showCertifications'])) $user->setShowCertifications((bool)$data['showCertifications']);
        if (isset($data['showEducation'])) $user->setShowEducation((bool)$data['showEducation']);
        if (isset($data['showAwards'])) $user->setShowAwards((bool)$data['showAwards']);

        $this->em->flush();
        return $this->json([
            'showEmail' => $user->isShowEmail(),
            'showProjects' => $user->isShowProjects(),
            'showExperience' => $user->isShowExperience(),
            'showCertifications' => $user->isShowCertifications(),
            'showEducation' => $user->isShowEducation(),
            'showAwards' => $user->isShowAwards(),
        ]);
    }

    #[Route('/roleres', methods: ['GET'])]
    public function getRoleResumes(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $conn = $this->em->getConnection();
        
        $sql = "
            SELECT role, projects, certificates, awards, experience 
            FROM resumes 
            WHERE user_id = :uid
        ";
        $rows = $conn->executeQuery($sql, ['uid' => $user->getId()])->fetchAllAssociative();

        $data = array_map(function ($row) use ($user) {
            return [
                'username' => $user->getUsername(),
                'role' => $row['role'],
                'stats' => [
                    'projects' => $row['projects'],
                    'certificates' => $row['certificates'],
                    'awards' => $row['awards'],
                    'experience' => $row['experience'],
                ]
            ];
        }, $rows);

        return $this->json($data);
    }

    #[Route('', methods: ['DELETE'])]
    public function deleteCurrentUser(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new Response(null, 500);
        }

        $this->em->remove($user);
        $this->em->flush();

        return new Response(null, 204);
    }
}
