<?php

declare(strict_types=1);

namespace App\Core;

class Config
{
    private static array $env = [];
    private static bool $loaded = false;

    public static function load(string $basePath): void
    {
        if (self::$loaded) {
            return;
        }

        $envFile = $basePath . '/.env';
        if (is_file($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#')) {
                        continue;
                    }
                    $pos = strpos($line, '=');
                    if ($pos === false) {
                        continue;
                    }
                    $key = trim(substr($line, 0, $pos));
                    $val = trim(substr($line, $pos + 1));
                    if (
                        (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                        (str_starts_with($val, "'") && str_ends_with($val, "'"))
                    ) {
                        $val = substr($val, 1, -1);
                    }
                    self::$env[$key] = $val;
                    if (getenv($key) === false) {
                        putenv("$key=$val");
                    }
                }
            }
        }

        self::$loaded = true;
        date_default_timezone_set(self::get('APP_TIMEZONE', 'Asia/Dhaka'));
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $val = getenv($key);
        if ($val !== false && $val !== '') {
            return $val;
        }
        return self::$env[$key] ?? $default;
    }

    public static function basePath(string $sub = ''): string
    {
        $root = dirname(__DIR__, 2);
        return $sub === '' ? $root : $root . '/' . ltrim($sub, '/');
    }
}
