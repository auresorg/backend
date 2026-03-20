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
        } else if ($value === 'present') {
            return null;
        } else if ($value === 'current') {
            return null;
        } else if ($value === "null") {
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
    public function bulk(Request $request, EntityManagerInterface $em): JsonResponse
    {
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
                $end = $parseDate($edu['endDate']);

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

            // fallback: if no best found, take zeroth education entry
            if ($best === null && !empty($payload['education'][0])) {
                $best = [
                    'data' => $payload['education'][0],
                    'duration' => 0,
                    'grade' => 0.0
                ];
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

                if (!$education->getStartDate()) {
                    $education->setStartDate(new \DateTime('1970-01-01'));
                }

                if (!$education->getEndDate()) {
                    $education->setEndDate(new \DateTime('1970-01-01'));
                }

                $em->persist($education);
            }
        }

        $roles = [];

        //calculate the skills array from projects
        //format: skill => count
        $skillCounts = [];

        /* ---------- PROJECTS ---------- */
        foreach ($payload['projects'] ?? [] as $index => $item) {
            $entity = new Project();
            $entity->setUser($user);

            // Validation
            if (empty($item['title']) || strlen(trim($item['title'])) > 255) {
                return new JsonResponse(['error' => 'Project name is required and must be at most 255 characters.'], 400);
            }
            //title is the "name"
            $item['name'] = $item['title'];

            // if (isset($item['repo']) && (empty(trim($item['repo'])) || strlen($item['repo']) > 140)) {
            //     return new JsonResponse(['error' => 'Project repo URL is required and must be at most 140 characters.'], 400);
            // }

            // if (isset($item['description']) && strlen($item['description']) < 100) {
            //     return new JsonResponse(['error' => 'Project description must be at least 100 characters.'], 400);
            // }

            if (isset($item['tech']) && (!is_array($item['tech']) || empty($item['tech']))) {
                return new JsonResponse(['error' => 'Project tech must be a non-empty array.'], 400);
            }

            foreach ($item as $key => $value) {
                if ($key === 'role') {
                    if (!is_array($value)) {
                        continue; // skip instead of failing bulk
                    }

                    $validRoles = array_column(RoleType::cases(), 'value');

                    $filtered = [];

                    foreach ($value as $r) {
                        $r = strtolower($r);

                        if (in_array($r, $validRoles, true)) {
                            $filtered[] = $r;
                            $roles[$r] = ($roles[$r] ?? 0) + 1;
                        }
                    }

                    // optional: if empty, just set []
                    $entity->setRole($filtered);

                    continue;
                }

                if ($key === 'url') {
                    if (!empty($value)) {
                        $url = trim($value);
                        if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
                            $url = "https://" . $url;
                        }
                        if (!filter_var($url, FILTER_VALIDATE_URL)) {
                            return new JsonResponse(['error' => 'Invalid project URL format.'], 400);
                        }
                        $entity->setUrl($url);
                    } else {
                        $entity->setUrl(null);
                    }
                    continue;
                }

                $setter = 'set' . ucfirst($key);
                if (method_exists($entity, $setter)) {
                    if (in_array($key, ['startDate', 'endDate', 'completedOn', 'date'], true)) {
                        $entity->$setter($this->parseDate($value));
                    } else {
                        $entity->$setter($value);
                    }
                }

                //count skills and increment user skills
                if ($key === 'tech' && is_array($value)) {
                    foreach ($value as $skill) {
                        $skillLower = strtolower($skill);
                        if (isset($skillCounts[$skillLower])) {
                            $skillCounts[$skillLower]++;
                        } else {
                            $skillCounts[$skillLower] = 1;
                        }
                        $user->incrementSkill($skill);
                    }
                }
            }

            if (!$entity->getStartDate()) {
                $entity->setStartDate(new \DateTime('1970-01-01'));
            }

            if (!$entity->getEndDate()) {
                $entity->setEndDate(new \DateTime('1970-01-01'));
            }

            $em->persist($entity);
            $user->incrementProjectsCount();
        }

        /* ---------- EXPERIENCE ---------- */
        foreach ($payload['experience'] ?? [] as $index => $item) {
            $entity = new Experience();
            $entity->setUser($user);

            // Validation
            if (isset($item['title']) && empty(trim($item['title']))) {
                return new JsonResponse(['error' => 'Experience title is required.'], 400);
            }

            if (isset($item['company']) && empty(trim($item['company']))) {
                return new JsonResponse(['error' => 'Experience company is required.'], 400);
            }

            if (isset($item['startDate']) && empty($item['startDate'])) {
                return new JsonResponse(['error' => 'Experience start date is required.'], 400);
            }

            foreach ($item as $key => $value) {
                if ($key === 'role') {
                    if (!is_array($value)) {
                        continue; // skip instead of failing bulk
                    }

                    $validRoles = array_column(RoleType::cases(), 'value');

                    $filtered = [];

                    foreach ($value as $r) {
                        $r = strtolower($r);

                        if (in_array($r, $validRoles, true)) {
                            $filtered[] = $r;
                            $roles[$r] = ($roles[$r] ?? 0) + 1;
                        }
                    }

                    // optional: if empty, just set []
                    $entity->setRole($filtered);

                    continue;
                }

                $setter = 'set' . ucfirst($key);
                if (method_exists($entity, $setter)) {
                    if (in_array($key, ['startDate', 'endDate', 'completedOn', 'date'], true)) {
                        $entity->$setter($this->parseDate($value));
                    } else {
                        $entity->$setter($value);
                    }
                }
            }

            if (!$entity->getStartDate()) {
                $entity->setStartDate(new \DateTime('1970-01-01'));
            }

            if (!$entity->getEndDate()) {
                $entity->setEndDate(new \DateTime('1970-01-01'));
            }

            $em->persist($entity);
            $user->setExperienceCount($user->getExperienceCount() + 1);
        }

        /* ---------- CERTIFICATIONS ---------- */
        foreach ($payload['certifications'] ?? [] as $index => $item) {
            $entity = new Certification();
            $entity->setUser($user);

            // Validation
            if (isset($item['title']) && empty(trim($item['title']))) {
                return new JsonResponse(['error' => 'Certification title is required.'], 400);
            }

            if (isset($item['issuer']) && empty(trim($item['issuer']))) {
                return new JsonResponse(['error' => 'Certification issuer is required.'], 400);
            }
            $item['platform'] = $item['issuer'];

            // if (!empty($item['url']) && !filter_var($item['url'], FILTER_VALIDATE_URL)) {
            //     return new JsonResponse(['error' => 'Certification URL must be a valid URL.'], 400);
            // }

            if (!empty($item['completedOn'])) {
                try {
                    $completedOn = new \DateTime($item['completedOn']);
                    if ($completedOn > new \DateTime()) {
                        return new JsonResponse(['error' => 'Certification completion date cannot be in the future.'], 400);
                    }
                } catch (\Exception $e) {
                    return new JsonResponse(['error' => 'Invalid certification completion date.'], 400);
                }
            }

            foreach ($item as $key => $value) {
                if ($key === 'role') {
                    if (!is_array($value)) {
                        continue; // skip instead of failing bulk
                    }

                    $validRoles = array_column(RoleType::cases(), 'value');

                    $filtered = [];

                    foreach ($value as $r) {
                        $r = strtolower($r);

                        if (in_array($r, $validRoles, true)) {
                            $filtered[] = $r;
                            $roles[$r] = ($roles[$r] ?? 0) + 1;
                        }
                    }

                    // optional: if empty, just set []
                    $entity->setRole($filtered);

                    continue;
                }

                $setter = 'set' . ucfirst($key);
                if (method_exists($entity, $setter)) {
                    if (in_array($key, ['startDate', 'endDate', 'completedOn', 'date'], true)) {
                        $entity->$setter($this->parseDate($value));
                    } else {
                        $entity->$setter($value);
                    }
                }
            }
            
            if (!$entity->getCompletedOn()) {
                $entity->setCompletedOn(new \DateTime('1970-01-01'));
            }

            $em->persist($entity);
            $user->incrementCertCount();
        }

        /* ---------- AWARDS ---------- */
        foreach ($payload['awards'] ?? [] as $index => $item) {
            $entity = new Award();
            $entity->setUser($user);

            // Validation
            if (isset($item['title']) && empty(trim($item['title']))) {
                return new JsonResponse(['error' => 'Award title is required.'], 400);
            }

            // if (isset($item['issuer']) && empty(trim($item['issuer']))) {
            //     return new JsonResponse(['error' => 'Award issuer is required.'], 400);
            // }

            // if (!isset($item['type'])) {
            //     return new JsonResponse(['error' => 'Award type is required.'], 400);
            // }

            if (!empty($item['date'])) {
                try {
                    $date = new \DateTime($item['date']);
                    if ($date > new \DateTime()) {
                        return new JsonResponse(['error' => 'Award date cannot be in the future.'], 400);
                    }
                } catch (\Exception $e) {
                    return new JsonResponse(['error' => 'Invalid award date.'], 400);
                }
            }

            foreach ($item as $key => $value) {
                if ($key === 'role') {
                    if (!is_array($value)) {
                        continue; // skip instead of failing bulk
                    }

                    $validRoles = array_column(RoleType::cases(), 'value');

                    $filtered = [];

                    foreach ($value as $r) {
                        $r = strtolower($r);

                        if (in_array($r, $validRoles, true)) {
                            $filtered[] = $r;
                            $roles[$r] = ($roles[$r] ?? 0) + 1;
                        }
                    }

                    // optional: if empty, just set []
                    $entity->setRole($filtered);

                    continue;
                }

                $setter = 'set' . ucfirst($key);
                if (method_exists($entity, $setter)) {
                    if (in_array($key, ['startDate', 'endDate', 'completedOn', 'date'], true)) {
                        $entity->$setter($this->parseDate($value));
                    } else {
                        $entity->$setter($value);
                    }
                }
            }

            $em->persist($entity);
            $user->setAwardsCount($user->getAwardsCount() + 1);
        }

        //set counts
        $user->setProjectsCount(count($payload['projects'] ?? []));
        $user->setExperienceCount(count($payload['experience'] ?? []));
        $user->setCertCount(count($payload['certifications'] ?? []));
        $user->setAwardsCount(count($payload['awards'] ?? []));

        $user->setSkills($skillCounts);
        /* ---------- SINGLE FLUSH ---------- */
        $em->flush();

        // Invalidate cache after successful update, for each role the user has in the updated entities
        foreach ($roles as $role => $count) {
            if ($count >= 3) {
                $this->cache->invalidateStandard(
                    $user->getId(),
                    $user->getUsername(),
                    $role
                );
            }
        }

        return $this->json(['ok' => true]);
    }
}