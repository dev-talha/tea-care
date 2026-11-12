<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Lang;

function e(mixed $val): string
{
    return htmlspecialchars((string)$val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function __(string $key, array $replace = []): string
{
    return Lang::trans($key, $replace);
}

function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $rows = Database::fetchAll('SELECT setting_key, setting_value FROM settings');
            foreach ($rows as $row) {
                $cache[$row['setting_key']] = (string)($row['setting_value'] ?? '');
            }
        } catch (\Throwable) {
            $cache = [];
        }
    }
    return $cache[$key] ?? $default;
}

function format_time_12(?string $time): string
{
    if (!$time) {
        return '';
    }
    $dt = \DateTimeImmutable::createFromFormat('H:i:s', $time) ?: \DateTimeImmutable::createFromFormat('H:i', $time);
    return $dt ? $dt->format('g:i A') : $time;
}

function cup_image_url(?string $path, ?int $userId = null): string
{
    if ($path && $userId) {
        return '/media/cup?user_id=' . $userId . '&v=' . substr(md5($path), 0, 8);
    }
    return '/assets/icons/cup-placeholder.svg';
}

function badge_beverage(string $bev): string
{
    return $bev === 'coffee' ? __('coffee') : __('tea');
}

function badge_sugar(string $sugar): string
{
    return match ($sugar) {
        'less' => __('sugar_less'),
        'none' => __('sugar_none'),
        default => __('sugar_normal'),
    };
}

function staff_short_name(?string $name): string
{
    $raw = trim((string)$name);
    if ($raw === '') {
        return '';
    }
    $clean = trim((string)preg_replace('/\s*\([^)]*\)/', '', $raw));
    return $clean !== '' ? $clean : $raw;
}
