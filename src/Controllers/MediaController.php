<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Request;

class MediaController
{
    public function cupPhoto(): void
    {
        Auth::requireAuth();
        $userId = (int)Request::get('user_id', 0);
        if ($userId <= 0) {
            http_response_code(404);
            exit;
        }

        $row = Database::fetchOne('SELECT cup_image FROM users WHERE id = ?', [$userId]);
        $rel = $row['cup_image'] ?? null;
        if (!$rel) {
            $this->servePlaceholder();
        }

        $uploadsRoot = realpath(Config::basePath('storage/uploads'));
        $clean = str_replace(['..', '\\', "\0"], ['', '/', ''], ltrim((string)$rel, '/'));
        $full = $uploadsRoot ? realpath($uploadsRoot . '/' . $clean) : false;
        if (!$full || !$uploadsRoot || !str_starts_with($full, $uploadsRoot . DIRECTORY_SEPARATOR) || !is_file($full)) {
            $this->servePlaceholder();
        }

        header('Content-Type: image/jpeg');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=86400');
        readfile($full);
        exit;
    }

    public function officeLogo(): void
    {
        $logo = setting('office_logo', '');
        if ($logo === '') {
            http_response_code(404);
            exit;
        }
        $uploadsRoot = realpath(Config::basePath('storage/uploads'));
        $clean = str_replace(['..', '\\', "\0"], ['', '/', ''], ltrim($logo, '/'));
        $full = $uploadsRoot ? realpath($uploadsRoot . '/' . $clean) : false;
        if (!$full || !$uploadsRoot || !str_starts_with($full, $uploadsRoot . DIRECTORY_SEPARATOR) || !is_file($full)) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: image/png');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=86400');
        readfile($full);
        exit;
    }

    private function servePlaceholder(): never
    {
        $svg = Config::basePath('public/assets/icons/cup-placeholder.svg');
        if (is_file($svg)) {
            header('Content-Type: image/svg+xml');
            header('Cache-Control: public, max-age=86400');
            readfile($svg);
        } else {
            http_response_code(404);
        }
        exit;
    }
}
