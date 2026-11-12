<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use DateTimeZone;

class ScheduleService
{
    public static function timezone(): DateTimeZone
    {
        $tz = 'Asia/Dhaka';
        try {
            $row = Database::fetchOne('SELECT setting_value FROM settings WHERE setting_key = ?', ['timezone']);
            if ($row && !empty($row['setting_value'])) {
                $tz = (string)$row['setting_value'];
            }
        } catch (\Throwable) {
        }
        return new DateTimeZone($tz);
    }

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', self::timezone());
    }

    public static function today(): string
    {
        return self::now()->format('Y-m-d');
    }

    public static function getSlots(): array
    {
        $rows = Database::fetchAll('SELECT * FROM service_slots ORDER BY service_time ASC');
        $slots = [];
        foreach ($rows as $r) {
            $slots[$r['slot_key']] = $r;
        }
        return $slots;
    }

    public static function getSlot(string $slotKey): ?array
    {
        return Database::fetchOne('SELECT * FROM service_slots WHERE slot_key = ?', [$slotKey]);
    }

    public static function getWorkingDays(): array
    {
        $row = Database::fetchOne('SELECT setting_value FROM settings WHERE setting_key = ?', ['working_days']);
        if ($row && !empty($row['setting_value'])) {
            $decoded = json_decode((string)$row['setting_value'], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return ['sun', 'mon', 'tue', 'wed', 'thu'];
    }

    public static function getHoliday(string $date): ?array
    {
        return Database::fetchOne(
            'SELECT * FROM holidays WHERE holiday_date = ? AND is_closed = 1',
            [$date]
        );
    }

    public static function getDayStatus(string $date): array
    {
        $holiday = self::getHoliday($date);
        if ($holiday) {
            return [
                'is_open' => false,
                'reason'  => 'holiday',
                'holiday' => $holiday,
            ];
        }

        $dt = new DateTimeImmutable($date, self::timezone());
        $dow = strtolower($dt->format('D')); // sun, mon, tue...
        $workingDays = self::getWorkingDays();
        if (!in_array($dow, $workingDays, true)) {
            return [
                'is_open' => false,
                'reason'  => 'non_working',
                'holiday' => null,
            ];
        }

        return [
            'is_open' => true,
            'reason'  => null,
            'holiday' => null,
        ];
    }

    public static function isCutoffPassed(array $slot, ?DateTimeImmutable $now = null): bool
    {
        $now ??= self::now();
        $cutoffStr = $now->format('Y-m-d') . ' ' . $slot['cutoff_time'];
        $cutoffDt = new DateTimeImmutable($cutoffStr, self::timezone());
        return $now >= $cutoffDt;
    }
}
