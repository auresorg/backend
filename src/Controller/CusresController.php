<?php

namespace App\Controller;

use App\Entity\Cusres;
use App\Entity\Project;
use App\Entity\Certification;
use App\Entity\Award;
use App\Entity\Experience;
use App\Entity\User;
use App\Repository\CusresRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_array;
use function strlen;

#[Route('/api/cusres')]
#[IsGranted('ROLE_USER')]
final class CusresController extends AbstractController
{
    /**
     * GET /
     * List user's cusres
     */
    #[Route('', methods: ['GET'])]
    public function index(CusresRepository $repo): JsonResponse
    {
        /** @var User $u */
        $u = $this->getUser();

        $rows = $repo->createQueryBuilder('c')
            ->select('c.slug, c.compiledAt, c.projects, c.certifications, c.awards, c.experiences')
            ->where('c.user = :u')
            ->setParameter('u', $u)
            ->orderBy('c.id', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $data = \array_map(static function (array $r): array {
            return [
                'slug' => $r['slug'],
                'compiledAt' => $r['compiledAt']?->format('c'),
                'dataUpdatedAt' => $r['compiledAt']?->format('c'),
                'stats' => [
                    'projects' => \count($r['projects'] ?? []),
                    'certificates' => \count($r['certifications'] ?? []),
                    'awards' => \count($r['awards'] ?? []),
                    'experience' => \count($r['experiences'] ?? []),
                ],
            ];
        }, $rows);

        return $this->json($data);
    }

    /**
     * POST /
     * Create cusres
     */
    #[Route('', methods: ['POST'])]
    public function create(
        Request $req,
        EntityManagerInterface $em,
        CusresRepository $repo
    ): JsonResponse {
        $u = $this->getUser();

        if ($repo->count(['user' => $u]) >= 3) {
            return $this->json(null, 429);

        }

        $data = json_decode($req->getContent(), true);

        if (!\is_array($data)) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }

        if (empty($data['slug']) || \strlen($data['slug']) > 10) {
            return $this->json(['error' => 'Invalid slug'], 400);
        }

        $projects = $this->assertOwnership($em, Project::class, $data['projects'] ?? [], $u);
        $certs = $this->assertOwnership($em, Certification::class, $data['certifications'] ?? [], $u);
        $awards = $this->assertOwnership($em, Award::class, $data['awards'] ?? [], $u);
        $exps = $this->assertOwnership($em, Experience::class, $data['experiences'] ?? [], $u);

        if ($projects === false || $certs === false || $awards === false || $exps === false) {
            return $this->json(['error' => 'Invalid foreign IDs'], 400);
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
            return $this->json(null, 409);
        }


        return $this->json(['id' => $c->getId(), 'slug' => $c->getSlug()], 201);
    }

    /**
     * DELETE /{id}
     */
    #[Route('/{slug}', methods: ['DELETE'])]
    public function delete(
        string $slug,
        CusresRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        $u = $this->getUser();
        
        $c = $repo->findOneBy(['slug' => $slug, 'user' => $u]);
        if (!$c) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $em->remove($c);
        $em->flush();

        return $this->json(null, 204);
    }

    /**
     * -------- helpers --------
     */

    private function assertOwnership(
        EntityManagerInterface $em,
        string $entity,
        array $ids,
        $u
    ): array|false {
        if (!\is_array($ids)) {
            return false;
        }

        if ($ids === []) {
            return [];
        }

        $rows = $em->createQuery(
            "SELECT e.id FROM $entity e WHERE e.user = :u AND e.id IN (:ids)"
        )
            ->setParameter('u', $u)
            ->setParameter('ids', $ids)
            ->getScalarResult();

        if (\count($rows) !== \count($ids)) {
            return false;
        }

        return array_map(static fn($r) => (int) $r['id'], $rows);
    }
}
