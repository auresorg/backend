<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/autocomplete')]
#[IsGranted('ROLE_USER')]
final class AutocompleteController extends AbstractController
{
    #[Route('', name: 'api_autocomplete_index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $type = $request->query->get('type');
        $q = $request->query->get('q', '');

        if (!$type) {
            return $this->json(['error' => 'Missing "type" parameter'], Response::HTTP_BAD_REQUEST);
        }

        $cleanQ = strtolower(str_replace('.', '', $q));
        $suggestions = [];

        if ($type === 'school' || $type === 'degree' || $type === 'field') {
            $conn = $entityManager->getConnection();
            $column = $type;
            
            $sql = "
                SELECT DISTINCT {$column} as value
                FROM education
                WHERE {$column} IS NOT NULL
                AND LOWER(REPLACE({$column}, '.', '')) LIKE :query
                LIMIT 10
            ";
            
            $stmt = $conn->prepare($sql);
            $resultSet = $stmt->executeQuery(['query' => '%' . $cleanQ . '%']);
            $results = $resultSet->fetchAllAssociative();
            
            foreach ($results as $row) {
                if (!empty($row['value'])) {
                    $suggestions[] = $row['value'];
                }
            }
        } elseif ($type === 'certification-title') {
            $conn = $entityManager->getConnection();
            
            $sql = "
                SELECT DISTINCT title, platform
                FROM certification
                WHERE title IS NOT NULL
                AND LOWER(REPLACE(title, '.', '')) LIKE :query
                LIMIT 10
            ";
            
            $stmt = $conn->prepare($sql);
            $resultSet = $stmt->executeQuery(['query' => '%' . $cleanQ . '%']);
            $results = $resultSet->fetchAllAssociative();
            
            foreach ($results as $row) {
                if (!empty($row['title'])) {
                    $suggestions[] = [
                        'value' => $row['title'],
                        'payload' => ['platform' => $row['platform']]
                    ];
                }
            }
        } elseif ($type === 'experience-title' || $type === 'experience-company') {
            $conn = $entityManager->getConnection();
            $column = $type === 'experience-title' ? 'title' : 'company';
            
            $sql = "
                SELECT DISTINCT {$column} as value
                FROM experience
                WHERE {$column} IS NOT NULL
                AND LOWER(REPLACE({$column}, '.', '')) LIKE :query
                LIMIT 10
            ";
            
            $stmt = $conn->prepare($sql);
            $resultSet = $stmt->executeQuery(['query' => '%' . $cleanQ . '%']);
            $results = $resultSet->fetchAllAssociative();
            
            foreach ($results as $row) {
                if (!empty($row['value'])) {
                    $suggestions[] = $row['value'];
                }
            }
        } else {
            return $this->json(['error' => 'Unsupported type'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($suggestions, Response::HTTP_OK);
    }
}
