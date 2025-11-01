<?php

namespace App\Controller;

use App\Entity\Education;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/education')]
#[IsGranted('ROLE_USER')]
final class EducationController extends AbstractController
{
    #[Route('', name: 'api_education_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response {
        /** @var User $user */
        $user = $this->getUser();

        $education = $user->getEducation();
        if (!$education) {
            //Create empty education if none exists
            $education = new Education();
            $education->setUser($user);
            $entityManager->persist($education);
            $entityManager->flush();
        }
        $educationData = [
            'id' => $education->getId(),
            'school' => $education->getSchool(),
            'degree' => $education->getDegree(),
            'field' => $education->getField(),
            'startDate' => $education->getStartDate()?->format('Y-m-d'),
            'endDate' => $education->getEndDate()?->format('Y-m-d'),
            'grade' => $education->getGrade(),
            'description' => $education->getDescription(),
        ];

        return $this->json($educationData, Response::HTTP_OK);
    }

    #[Route('', name: 'api_education_edit', methods: ['PUT', 'PATCH'])]
    public function edit(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $education = $user->getEducation();

        if (!$education) {
            return $this->json(null, Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        // TODO: update education fields here manually
        $errors = [];

        if (isset($data['school'])) {
            $education->setSchool($data['school']);
        }
        if (isset($data['degree'])) {
            if (!preg_match('/^[a-zA-Z\s\-&,.]+$/', $data['degree'])) {
                $errors['degree'] = 'Degree must contain only alphabetic characters, spaces, hyphens, ampersands, commas, or periods.';
            } else {
                $education->setDegree($data['degree']);
            }
        }
        if (isset($data['field'])) {
            if (!preg_match('/^[a-zA-Z\s\-&,.]+$/', $data['field'])) {
                $errors['field'] = 'Field must contain only alphabetic characters, spaces, hyphens, ampersands, commas, or periods.';
            } else {
                $education->setField($data['field']);
            }
        }

        try {
            if (isset($data['startDate'])) {
                $education->setStartDate(new \DateTime($data['startDate']));
            }
        } catch (\Exception $e) {
            $errors['startDate'] = 'Invalid start date.';
        }

        try {
            if (isset($data['endDate'])) {
                $education->setEndDate(new \DateTime($data['endDate']));
            }
        } catch (\Exception $e) {
            $errors['endDate'] = 'Invalid end date.';
        }

        if (isset($data['grade'])) {
            $education->setGrade($data['grade']);
        }
        if (isset($data['description'])) {
            $education->setDescription($data['description']);
        }

        if (!empty($errors)) {
            return $this->json(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }
        
        $entityManager->persist($education);
        $entityManager->flush();

        return $this->json(null, Response::HTTP_OK);
    }
}
