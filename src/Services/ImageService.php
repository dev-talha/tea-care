<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

class ImageService
{
    public static function saveCupPhoto(array $file, int $userId): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        if (($file['size'] ?? 0) > 6 * 1024 * 1024) {
            throw new \RuntimeException('Image file is too large (max 6MB).');
        }

        $tmp = $file['tmp_name'] ?? '';
        if (!is_uploaded_file($tmp)) {
            throw new \RuntimeException('Invalid upload.');
        }

        $info = @getimagesize($tmp);
        if ($info === false) {
            throw new \RuntimeException('File must be a valid image (JPG, PNG, or WebP).');
        }

        $mime = $info['mime'] ?? '';
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmp),
            'image/png'  => @imagecreatefrompng($tmp),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
            default      => false,
        };

        if (!$src) {
            throw new \RuntimeException('Unsupported image format. Please use JPG, PNG, or WebP.');
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        $size = 240;

        $dst = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);

        // Center crop to square
        $minDim = min($srcW, $srcH);
        $srcX = (int)(($srcW - $minDim) / 2);
        $srcY = (int)(($srcH - $minDim) / 2);

        imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $size, $size, $minDim, $minDim);
        imagedestroy($src);

        $dir = Config::basePath('storage/uploads/cups');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $filename = 'cup_' . $userId . '_' . bin2hex(random_bytes(6)) . '.jpg';
        $fullPath = $dir . '/' . $filename;
        imagejpeg($dst, $fullPath, 78);
        imagedestroy($dst);

        return 'cups/' . $filename;
    }

    public static function saveLogo(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        $tmp = $file['tmp_name'] ?? '';
        if (!is_uploaded_file($tmp)) {
            return null;
        }
        $info = @getimagesize($tmp);
        if ($info === false) {
            throw new \RuntimeException('Invalid logo image.');
        }
        $mime = $info['mime'] ?? '';
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmp),
            'image/png'  => @imagecreatefrompng($tmp),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
            default      => false,
        };
        if (!$src) {
            throw new \RuntimeException('Unsupported logo format.');
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        $targetH = 128;
        $targetW = max(128, (int)round($srcW * ($targetH / max(1, $srcH))));
        if ($targetW > 360) {
            $targetW = 360;
        }

        $dst = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $trans = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefill($dst, 0, 0, $trans);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetW, $targetH, $srcW, $srcH);
        imagedestroy($src);

        $dir = Config::basePath('storage/uploads/logo');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $filename = 'office_logo_' . bin2hex(random_bytes(4)) . '.png';
        imagepng($dst, $dir . '/' . $filename, 6);
        imagedestroy($dst);

        return 'logo/' . $filename;
    }

    public static function deleteStoredFile(?string $relativePath): void
    {
        if (!$relativePath) {
            return;
        }
        $clean = str_replace('..', '', ltrim($relativePath, '/'));
        $full = Config::basePath('storage/uploads/' . $clean);
        if (is_file($full)) {
            @unlink($full);
        }
    }
}
