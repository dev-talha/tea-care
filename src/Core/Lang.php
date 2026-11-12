<?php

declare(strict_types=1);

namespace App\Core;

class Lang
{
    private static string $current = 'en';
    private static array $translations = [];

    public static function init(?string $userLang = null): void
    {
        self::set('en', false);
    }

    public static function set(string $lang, bool $persistCookie = false): void
    {
        self::$current = 'en';
        Session::set('lang', 'en');

        if (!isset(self::$translations['en'])) {
            $file = Config::basePath('resources/lang/en.php');
            self::$translations['en'] = is_file($file) ? require $file : [];
        }
    }

    public static function current(): string
    {
        return self::$current;
    }

    public static function trans(string $key, array $replace = []): string
    {
        $dict = self::$translations[self::$current] ?? [];
        $text = $dict[$key] ?? $key;
        foreach ($replace as $k => $v) {
            $text = str_replace(':' . $k, (string)$v, $text);
        }
        return $text;
    }
}
