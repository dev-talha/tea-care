<?php

declare(strict_types=1);

namespace App\Core;

class Request
{
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function uri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $pos = strpos($uri, '?');
        if ($pos !== false) {
            $uri = substr($uri, 0, $pos);
        }
        return '/' . trim($uri, '/');
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public static function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public static function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!$raw) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function isAjax(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        return strtolower($xhr) === 'xmlhttprequest'
            || str_contains($accept, 'application/json')
            || str_contains($ct, 'application/json');
    }

    public static function ip(): string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        // Only trust forwarding headers when the direct peer is a local/private reverse proxy
        $isTrustedProxy = filter_var(
            $remote,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;

        if ($isTrustedProxy) {
            foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP'] as $hdr) {
                $val = trim((string)($_SERVER[$hdr] ?? ''));
                if ($val !== '' && filter_var($val, FILTER_VALIDATE_IP)) {
                    return $val;
                }
            }
        }

        return $remote;
    }
}
