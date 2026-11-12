<?php

declare(strict_types=1);

namespace App\Core;

class Auth
{
    private static ?array $user = null;
    private static bool $resolved = false;

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;

        $userId = Session::get('user_id');
        if (!$userId) {
            return null;
        }

        $user = Database::fetchOne(
            'SELECT u.*, d.name AS department_name, r.name AS room_name
             FROM users u
             LEFT JOIN departments d ON d.id = u.department_id
             LEFT JOIN rooms r ON r.id = u.room_id
             WHERE u.id = ?',
            [(int)$userId]
        );

        if (!$user || $user['status'] !== 'active') {
            Session::forget('user_id');
            return null;
        }

        self::$user = $user;
        return $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int)$u['id'] : null;
    }

    public static function role(): ?string
    {
        $u = self::user();
        return $u['role'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function isStaff(): bool
    {
        return self::role() === 'staff';
    }

    public static function isEmployee(): bool
    {
        return self::role() === 'employee';
    }

    public static function canOrderForGuests(): bool
    {
        $u = self::user();
        if (!$u) {
            return false;
        }
        return $u['role'] === 'admin' || (int)($u['can_order_for_guests'] ?? 0) === 1;
    }

    public static function login(int $userId): void
    {
        Session::regenerate();
        Session::set('user_id', $userId);
        self::$resolved = false;
        self::$user = null;
    }

    public static function logout(): void
    {
        self::$user = null;
        self::$resolved = true;
        Session::destroy();
    }

    public static function requireAuth(): array
    {
        $user = self::user();
        if (!$user) {
            if (Request::isAjax()) {
                Response::json(['ok' => false, 'error' => 'Unauthorized'], 401);
            }
            Response::redirect('/login');
        }
        return $user;
    }

    public static function requireRole(array $roles): array
    {
        $user = self::requireAuth();
        if (!in_array($user['role'], $roles, true)) {
            if (Request::isAjax()) {
                Response::json(['ok' => false, 'error' => 'Forbidden'], 403);
            }
            http_response_code(403);
            echo View::render('partials/forbidden', ['user' => $user]);
            exit;
        }
        return $user;
    }

    public static function requireGuestPermission(): array
    {
        $user = self::requireAuth();
        if (!self::canOrderForGuests()) {
            if (Request::isAjax()) {
                Response::json(['ok' => false, 'error' => 'Forbidden'], 403);
            }
            http_response_code(403);
            echo View::render('partials/forbidden', ['user' => $user]);
            exit;
        }
        return $user;
    }

    public static function isRateLimited(string $ip, string $employeeId): bool
    {
        $windowStart = date('Y-m-d H:i:s', time() - 600);
        $ipCount = Database::fetchOne(
            'SELECT COUNT(*) AS cnt FROM login_attempts WHERE ip_address = ? AND attempted_at >= ?',
            [$ip, $windowStart]
        );
        $empCount = Database::fetchOne(
            'SELECT COUNT(*) AS cnt FROM login_attempts WHERE employee_id = ? AND attempted_at >= ?',
            [$employeeId, $windowStart]
        );
        return ((int)($ipCount['cnt'] ?? 0) >= 12) || ((int)($empCount['cnt'] ?? 0) >= 8);
    }

    public static function recordFailedAttempt(string $ip, string $employeeId): void
    {
        Database::insert(
            'INSERT INTO login_attempts (ip_address, employee_id, attempted_at) VALUES (?, ?, NOW())',
            [$ip, $employeeId]
        );
    }

    public static function clearAttempts(string $ip, string $employeeId): void
    {
        Database::execute(
            'DELETE FROM login_attempts WHERE employee_id = ? OR attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)',
            [$employeeId]
        );
    }
}
