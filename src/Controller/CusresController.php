<?php

namespace App\Controller;

use App\Entity\Cusres;
use App\Entity\{Project, Certification, Award, Experience, User};
use App\Repository\CusresRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use function is_array;

#[Route('/api/cusres')]
#[IsGranted('ROLE_USER')]
final class CusresController extends AbstractController
{
    /* ---------------------------------------------------- */
    /* LIST */
    /* ---------------------------------------------------- */

    #[Route('', methods: ['GET'])]
    public function index(CusresRepository $repo): JsonResponse
    {
        /** @var User $u */
        $u = $this->getUser();

        $rows = $repo->createQueryBuilder('c')
            ->select('c.slug, c.projects, c.certifications, c.awards, c.experiences')
            ->where('c.user = :u')
            ->setParameter('u', $u)
            ->orderBy('c.id', 'DESC')
            ->getQuery()
            ->getArrayResult();

        return $this->json(array_map(static fn(array $r) => [
            'slug' => $r['slug'],
            'stats' => [
                'projects' => count($r['projects'] ?? []),
                'certifications' => count($r['certifications'] ?? []),
                'awards' => count($r['awards'] ?? []),
                'experiences' => count($r['experiences'] ?? []),
            ],
        ], $rows));
    }

    /* ---------------------------------------------------- */
    /* CREATE */
    /* ---------------------------------------------------- */

    #[Route('', methods: ['POST'])]
    public function create(
        Request $req,
        EntityManagerInterface $em,
        CusresRepository $repo
    ): JsonResponse {
        /** @var User $u */
        $u = $this->getUser();

        if ($repo->count(['user' => $u]) >= 3) {
            return $this->json(['error' => 'Limit reached'], 429);
        }

        $data = json_decode($req->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }

        if (empty($data['slug']) || strlen($data['slug']) > 10) {
            return $this->json(['error' => 'Invalid slug'], 400);
        }

        $projects = $this->assertOwnership($em, Project::class, $data['projects'] ?? [], $u);
        $certs = $this->assertOwnership($em, Certification::class, $data['certifications'] ?? [], $u);
        $awards = $this->assertOwnership($em, Award::class, $data['awards'] ?? [], $u);
        $exps = $this->assertOwnership($em, Experience::class, $data['experiences'] ?? [], $u);

        if ($projects === false || $certs === false || $awards === false || $exps === false) {
            return $this->json(['error' => 'Invalid entity IDs'], 400);
        }

        try {
            $c = (new Cusres())
                ->setUser($u)
                ->setSlug($data['slug'])
                ->setProjects($projects)
                ->setCertifications($certs)
                ->setAwards($awards)
                ->setExperiences($exps);

            $em->persist($c);
            $em->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->json(['error' => 'Slug already exists'], 409);
        }

        return $this->json([
            'id' => $c->getId(),
            'slug' => $c->getSlug(),
        ], 201);
    }

    /* ---------------------------------------------------- */
    /* DELETE */
    /* ---------------------------------------------------- */

    #[Route('/{slug}', methods: ['DELETE'])]
    public function delete(
        string $slug,
        CusresRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var User $u */
        $u = $this->getUser();

        $c = $repo->findOneBy(['slug' => $slug, 'user' => $u]);
        if (!$c) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $em->remove($c);
        $em->flush();

        return $this->json(null, 204);
    }

    /* ---------------------------------------------------- */
    /* HELPERS */
    /* ---------------------------------------------------- */

    private function assertOwnership(
        EntityManagerInterface $em,
        string $entity,
        array $ids,
        User $u
    ): array|false {
        if (!is_array($ids))
            return false;
        if ($ids === [])
            return [];

        $rows = $em->createQuery(
            "SELECT e.id FROM $entity e WHERE e.user = :u AND e.id IN (:ids)"
        )
            ->setParameter('u', $u)
            ->setParameter('ids', $ids)
            ->getScalarResult();

        if (count($rows) !== count($ids)) {
            return false;
        }

        return array_map(fn($r) => (int) $r['id'], $rows);
    }
}
