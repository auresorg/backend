<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use function in_array;

final class ResumeCacheInvalidatorHelper
{
    private string $serverlessUrl;

    public function __construct(private Connection $db)
    {
        $this->serverlessUrl = $_ENV['SERVERLESS_URL']
            ?? $_SERVER['SERVERLESS_URL']
            ?? throw new \RuntimeException('SERVERLESS_URL not set');
    }

    public function invalidateStandard(int $userId, string $username, string $role): void
    {
        $this->fire([
            'type' => 'standard',
            'userId' => $userId,
            'username' => $username,
            'role' => $role,
        ]);
    }

    public function invalidateCustom(int $userId, string $slug): void
    {
        $this->fire([
            'type' => 'custom',
            'userId' => $userId,
            'slug' => $slug,
        ]);
    }

    public function deleteStandard(int $userId, string $username, string $role): void
    {
        $this->fire([
            'action' => 'delete',
            'type' => 'standard',
            'userId' => $userId,
            'username' => $username,
            'role' => $role,
        ]);
    }

    public function deleteCustom(int $userId, string $slug, string $username): void
    {
        $this->fire([
            'action' => 'delete',
            'type' => 'custom',
            'userId' => $userId,
            'username' => $username,
            'slug' => $slug,
        ]);
    }

    /**
     * Generic invalidation for ANY entity update
     *
     * $entityColumn must be one of:
     *  projects | experiences | certifications | awards
     */
    public function invalidateAfterEntityUpdate(int $userId, string $username, array $oldRoles, array $newRoles, string $entityColumn, int $entityId): void
    {
        $rolesToInvalidate = array_unique(array_merge($oldRoles, $newRoles));
        foreach ($rolesToInvalidate as $roleToInvalidate) {
            $this->invalidateStandard($userId, $username, $roleToInvalidate);
        }

        if (
            !in_array($entityColumn, [
                'projects',
                'experiences',
                'certifications',
                'awards',
            ], true)
        ) {
            return;
        }

        $sql = "
            SELECT slug
            FROM cusres
            WHERE user_id = :uid
              AND {$entityColumn}::jsonb @> :eid::jsonb
        ";

        $rows = $this->db->fetchAllAssociative($sql, [
            'uid' => $userId,
            'eid' => json_encode([$entityId]),
        ]);

        foreach ($rows as $row) {
            $this->invalidateCustom($userId, $row['slug']);
        }
    }

    public function invalidateInUse(int $userId, string $username): void
    {
        $sql = "
            (
                SELECT 
                    'standard' AS type,
                    role,
                    NULL::text AS slug
                FROM resumes
                WHERE user_id = :uid
                AND (
                        projects +
                        certificates +
                        awards +
                        experience
                    ) >= 3
            )

            UNION ALL

            (
                SELECT
                    'custom' AS type,
                    NULL::text AS role,
                    slug
                FROM cusres
                WHERE user_id = :uid
                AND (
                        json_array_length(projects) +
                        json_array_length(certifications) +
                        json_array_length(awards) +
                        json_array_length(experiences)
                    ) >= 3
            )
        ";

        $rows = $this->db->fetchAllAssociative($sql, [
            'uid' => $userId,
        ]);

        foreach ($rows as $row) {
            if ($row['type'] === 'standard' && $row['role']) {
                $this->invalidateStandard($userId, $username, $row['role']);
            }

            if ($row['type'] === 'custom' && $row['slug']) {
                $this->invalidateCustom($userId, $row['slug']);
            }
        }
    }

    private function fire(array $payload): void
    {
        $payload['_call_id'] = uniqid('php_', true);

        $ch = curl_init($this->serverlessUrl);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_CONNECTTIMEOUT_MS => 300,
            CURLOPT_TIMEOUT_MS => 300,

            CURLOPT_FORBID_REUSE => true,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_NOSIGNAL => true,
        ]);

        curl_exec($ch);
        curl_close($ch);
    }

}
