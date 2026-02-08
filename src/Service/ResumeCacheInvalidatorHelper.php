<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use function in_array;

final class ResumeCacheInvalidatorHelper
{
    private string $serverlessUrl;

    public function __construct(private Connection $db) {
        $this->serverlessUrl = $_ENV['SERVERLESS_URL']
            ?? $_SERVER['SERVERLESS_URL']
            ?? throw new \RuntimeException('SERVERLESS_URL not set');
    }

    public function invalidateStandard(int $userId, string $username, string $role): void {
        $this->fire([
            'type' => 'standard',
            'userId' => $userId,
            'username' => $username,
            'role' => $role,
        ]);
    }

    public function invalidateCustom(int $userId, string $slug): void {
        $this->fire([
            'type' => 'custom',
            'userId' => $userId,
            'slug' => $slug,
        ]);
    }

    /**
     * Generic invalidation for ANY entity update
     *
     * $entityColumn must be one of:
     *  projects | experiences | certifications | awards
     */
    public function invalidateAfterEntityUpdate(int $userId, string $username, ?string $oldRole, ?string $newRole, string $entityColumn, int $entityId): void {
        if ($oldRole && $oldRole !== $newRole) {
            $this->invalidateStandard($userId, $username, $oldRole);
        }

        if ($newRole) {
            $this->invalidateStandard($userId, $username, $newRole);
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

    private function fire(array $payload): void
    {

        $payload['_call_id'] = uniqid('php_', true);

        $ch = curl_init($this->serverlessUrl);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_CONNECTTIMEOUT_MS => 1200,
            CURLOPT_TIMEOUT_MS => 1200,
            CURLOPT_FORBID_REUSE => true,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_NOSIGNAL => true,
        ]);

        curl_exec($ch);
        curl_close($ch);
    }
}
