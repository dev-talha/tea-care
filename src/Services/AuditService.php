<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;

class AuditService
{
    public static function log(?int $userId, string $actorName, string $action, string $entityType, ?int $entityId = null, array $details = []): void
    {
        try {
            Database::insert(
                'INSERT INTO audit_logs (user_id, actor_name, action, entity_type, entity_id, details, ip_address, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    $userId,
                    $actorName,
                    $action,
                    $entityType,
                    $entityId,
                    $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                    Request::ip(),
                ]
            );
        } catch (\Throwable) {
            // Audit failure should never crash user flow
        }
    }
}
