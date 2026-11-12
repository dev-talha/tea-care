<?php

declare(strict_types=1);

namespace App\Core;

class Validator
{
    public static function str(mixed $val, int $max = 255): string
    {
        $s = trim((string)$val);
        if (mb_strlen($s) > $max) {
            $s = mb_substr($s, 0, $max);
        }
        return $s;
    }

    public static function enum(mixed $val, array $allowed, string $default): string
    {
        $s = trim((string)$val);
        return in_array($s, $allowed, true) ? $s : $default;
    }

    public static function intRange(mixed $val, int $min, int $max, int $default): int
    {
        if (!is_numeric($val)) {
            return $default;
        }
        $n = (int)$val;
        if ($n < $min) {
            return $min;
        }
        if ($n > $max) {
            return $max;
        }
        return $n;
    }
}
