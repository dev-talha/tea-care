<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

class AnnouncementService
{
    public static function getActiveForDate(string $date): array
    {
        return Database::fetchAll(
            "SELECT a.*, u.full_name AS author_name
             FROM staff_announcements a
             LEFT JOIN users u ON u.id = a.created_by
             WHERE a.announce_date = ? AND a.is_active = 1
             ORDER BY a.created_at DESC, a.id DESC",
            [$date]
        );
    }

    public static function create(
        int $actorId,
        string $date,
        string $scope,
        string $type,
        ?string $adjustedTime,
        string $reason
    ): int {
        $scope = in_array($scope, ['all', 'morning', 'afternoon'], true) ? $scope : 'all';
        $type = in_array($type, ['unavailable', 'time_change', 'info'], true) ? $type : 'unavailable';

        $cleanTime = null;
        if ($type === 'time_change' && $adjustedTime !== null && $adjustedTime !== '') {
            if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $adjustedTime)) {
                $cleanTime = strlen($adjustedTime) === 5 ? $adjustedTime . ':00' : $adjustedTime;
            }
        }

        if ($type === 'time_change' && $cleanTime === null) {
            throw new \RuntimeException('Please pick an adjusted service time.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new \RuntimeException('Please enter a short reason for the announcement.');
        }

        // Deactivate older active announcements matching same scope (or all) on same date so dashboard stays clean
        if ($scope === 'all') {
            Database::execute(
                'UPDATE staff_announcements SET is_active = 0, updated_at = NOW() WHERE announce_date = ? AND is_active = 1',
                [$date]
            );
        } else {
            Database::execute(
                'UPDATE staff_announcements SET is_active = 0, updated_at = NOW() WHERE announce_date = ? AND scope = ? AND is_active = 1',
                [$date, $scope]
            );
        }

        $id = (int)Database::insert(
            'INSERT INTO staff_announcements (announce_date, scope, announce_type, adjusted_time, reason, is_active, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 1, ?, NOW(), NOW())',
            [$date, $scope, $type, $cleanTime, mb_substr($reason, 0, 290), $actorId]
        );

        AuditService::log($actorId, 'Staff', 'create_announcement', 'staff_announcement', $id, [
            'date' => $date,
            'scope' => $scope,
            'type' => $type,
            'adjusted_time' => $cleanTime,
        ]);

        return $id;
    }

    public static function deactivate(int $id, int $actorId): void
    {
        Database::execute(
            'UPDATE staff_announcements SET is_active = 0, updated_at = NOW() WHERE id = ?',
            [$id]
        );
        AuditService::log($actorId, 'Staff', 'clear_announcement', 'staff_announcement', $id);
    }

    public static function findSlotNotice(array $announcements, string $slotKey): ?array
    {
        foreach ($announcements as $ann) {
            if ($ann['scope'] === $slotKey || $ann['scope'] === 'all') {
                return $ann;
            }
        }
        return null;
    }
}
