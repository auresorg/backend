<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\ResumeCacheInvalidatorHelper;
use App\Entity\RoleType;
use App\Entity\User;
use App\Entity\Education;
use App\Entity\Project;
use App\Entity\Experience;
use App\Entity\Certification;
use App\Entity\Award;
use function count;
use function in_array;

#[Route('/api')]
final class BulkController extends AbstractController
{
    private ResumeCacheInvalidatorHelper $cache;

    public function __construct(ResumeCacheInvalidatorHelper $cache)
    {
        $this->cache = $cache;
    }

    private function parseDate(?string $value): ?\DateTime
    {
        if (!$value) {
            return null;
        }

        // YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return new \DateTime($value);
        }

        // YYYY-MM
        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            return new \DateTime($value . '-01');
        }

        // YYYY
        if (preg_match('/^\d{4}$/', $value)) {
            return new \DateTime($value . '-01-01');
        }

        throw new \InvalidArgumentException('Invalid date format: ' . $value);
    }

    #[Route('/bulk', name: 'app_bulk_import', methods: ['POST'])]
    public function bulk(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var User|null **/
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        /* ---------- USER ---------- */
        if (!empty($payload['user'])) {
            foreach ($payload['user'] as $key => $value) {
                $setter = 'set' . ucfirst($key);
                if (method_exists($user, $setter)) {
                    $user->$setter($value);
                }
            }
        }

        /* ---------- EDUCATION ---------- */
        if (!empty($payload['education']) && is_array($payload['education'])) {

            // helper: normalize frontend date string → DateTime or null
            $parseDate = function (?string $value): ?\DateTimeInterface {
                if (!$value) {
                    return null;
                }

                // YYYY
                if (preg_match('/^\d{4}$/', $value)) {
                    return \DateTime::createFromFormat('Y-m', $value . '-01');
                }

                // YYYY-MM or YYYY-MM-DD → cut to YYYY-MM
                if (preg_match('/^\d{4}-\d{2}/', $value)) {
                    return \DateTime::createFromFormat('Y-m', substr($value, 0, 7));
                }

                return null;
            };

            $best = null;

            foreach ($payload['education'] as $edu) {
                if (empty($edu['startDate']) || empty($edu['endDate'])) {
                    continue;
                }

                $start = $parseDate($edu['startDate']);
                $end   = $parseDate($edu['endDate']);

                if (!$start || !$end) {
                    continue;
                }

                $duration = $start->diff($end)->days;
                $grade = isset($edu['grade']) ? (float) $edu['grade'] : 0.0;

                if (
                    $best === null ||
                    $duration > $best['duration'] ||
                    ($duration === $best['duration'] && $grade > $best['grade'])
                ) {
                    $best = [
                        'data' => $edu,
                        'duration' => $duration,
                        'grade' => $grade
                    ];
                }
            }

            if ($best !== null) {
                $education = $em->getRepository(Education::class)
                    ->findOneBy(['user' => $user]) ?? new Education();

                $education->setUser($user);

                foreach ($best['data'] as $key => $value) {
                    $setter = 'set' . ucfirst($key);

                    if (!method_exists($education, $setter)) {
                        continue;
                    }

                    if (in_array($key, ['startDate', 'endDate'], true)) {
                        $education->$setter($parseDate($value));
                    } else {
                        $education->$setter($value);
                    }
                }

                $em->persist($education);
            }
        }

        $roles = [];

        //calculate the skills array from projects
        //format: skill => count
        $skillCounts = [];

        /* ---------- PROJECTS ---------- */
        foreach ($payload['projects'] ?? [] as $item) {
            $entity = new Project();
            $entity->setUser($user);

            foreach ($item as $key => $value) {

                if ($key === 'role') {
                    if (
                        $value !== null &&
                        !in_array($value, array_column(RoleType::cases(), 'value'), true)
                    ) {
                        return new JsonResponse(
                            ['error' => 'Invalid role'],
                            400
                        );
                    }

                    $entity->setRole($value ? RoleType::from($value) : null);

                    //if it is not in the roles array, add it
                    if ($value && !in_array($value, $roles, true)) {
                        $roles[] = $value;
                    }
                    continue;
                }

                $setter = 'set' . ucfirst($key);
                if (method_exists($entity, $setter)) {
                    if (
                        in_array($key, ['startDate', 'endDate', 'completedOn', 'date'], true)
                    ) {
                        $entity->$setter($this->parseDate($value));
                    } else {
                        $entity->$setter($value);
                    }
                }

                //count skills
                if ($key === 'tech' && is_array($value)) {
                    foreach ($value as $skill) {
                        $skillLower = strtolower($skill);
                        if (isset($skillCounts[$skillLower])) {
                            $skillCounts[$skillLower]++;
                        } else {
                            $skillCounts[$skillLower] = 1;
                        }
                    }
                }
            }

            $em->persist($entity);
        }

        /* ---------- EXPERIENCE ---------- */
        foreach ($payload['experience'] ?? [] as $item) {
            $entity = new Experience();
            $entity->setUser($user);

            foreach ($item as $key => $value) {

                if ($key === 'role') {
                    if (
                        $value !== null &&
                        !in_array($value, array_column(RoleType::cases(), 'value'), true)
                    ) {
                        return new JsonResponse(
                            ['error' => 'Invalid role'],
                            400
                        );
                    }

                    if ($value && !in_array($value, $roles, true)) {
                        $roles[] = $value;
                    }

                    $entity->setRole($value ? RoleType::from($value) : null);
                    continue;
                }

                $setter = 'set' . ucfirst($key);
                if (method_exists($entity, $setter)) {
                    if (
                        in_array($key, ['startDate', 'endDate', 'completedOn', 'date'], true)
                    ) {
                        $entity->$setter($this->parseDate($value));
                    } else {
                        $entity->$setter($value);
                    }
                }
            }

            $em->persist($entity);
        }

        /* ---------- CERTIFICATIONS ---------- */
        foreach ($payload['certifications'] ?? [] as $item) {
            $entity = new Certification();
            $entity->setUser($user);

            foreach ($item as $key => $value) {

                if ($key === 'role') {
                    if (
                        $value !== null &&
                        !in_array($value, array_column(RoleType::cases(), 'value'), true)
                    ) {
                        return new JsonResponse(
                            ['error' => 'Invalid role'],
                            400
                        );
                    }

                    if ($value && !in_array($value, $roles, true)) {
                        $roles[] = $value;
                    }

                    $entity->setRole($value ? RoleType::from($value) : null);
                    continue;
                }

                $setter = 'set' . ucfirst($key);
                if (method_exists($entity, $setter)) {
                    if (
                        in_array($key, ['startDate', 'endDate', 'completedOn', 'date'], true)
                    ) {
                        $entity->$setter($this->parseDate($value));
                    } else {
                        $entity->$setter($value);
                    }
                }
            }

            $em->persist($entity);
        }

        /* ---------- AWARDS ---------- */
        foreach ($payload['awards'] ?? [] as $item) {
            $entity = new Award();
            $entity->setUser($user);

            foreach ($item as $key => $value) {

                if ($key === 'role') {
                    if (
                        $value !== null &&
                        !in_array($value, array_column(RoleType::cases(), 'value'), true)
                    ) {
                        return new JsonResponse(
                            ['error' => 'Invalid role'],
                            400
                        );
                    }

                    if ($value && !in_array($value, $roles, true)) {
                        $roles[] = $value;
                    }

                    $entity->setRole($value ? RoleType::from($value) : null);
                    continue;
                }

                $setter = 'set' . ucfirst($key);
                if (method_exists($entity, $setter)) {
                    if (
                        in_array($key, ['startDate', 'endDate', 'completedOn', 'date'], true)
                    ) {
                        $entity->$setter($this->parseDate($value));
                    } else {
                        $entity->$setter($value);
                    }
                }
            }

            $em->persist($entity);
        }

        //set counts
        $user->setProjectsCount(count($payload['projects'] ?? []));
        $user->setExperienceCount(count($payload['experience'] ?? []));
        $user->setCertCount(count($payload['certifications'] ?? []));
        $user->setAwardsCount(count($payload['awards'] ?? []));

        $user->setSkills($skillCounts);
        /* ---------- SINGLE FLUSH ---------- */
        $em->flush();

        $response = new JsonResponse(['ok' => true]);

        // if (function_exists('fastcgi_finish_request')) {
        //     fastcgi_finish_request();
        // }
        // Invalidate cache after successful update, for each role the user has in the updated entities
        foreach ($roles as $role) {
            $this->cache->invalidateStandard(
                $user->getId(),
                $user->getUsername(),
                $role
            );
        }

        return $response;
    }
}