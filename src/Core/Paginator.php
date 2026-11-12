<?php

declare(strict_types=1);

namespace App\Core;

class Paginator
{
    public const ALLOWED_PER_PAGE = [25, 50, 100, 500];
    public const DEFAULT_PER_PAGE = 25;

    public static function resolvePerPage(string $param = 'per_page', int $default = self::DEFAULT_PER_PAGE): int
    {
        $raw = (int)Request::get($param, $default);
        return in_array($raw, self::ALLOWED_PER_PAGE, true) ? $raw : $default;
    }

    /**
     * Compute clamped pagination bounds using server-side LIMIT/OFFSET parameters.
     */
    public static function paginate(
        int $totalItems,
        string $pageParam = 'page',
        string $perPageParam = 'per_page',
        int $defaultPerPage = self::DEFAULT_PER_PAGE
    ): array {
        $perPage = self::resolvePerPage($perPageParam, $defaultPerPage);
        $totalItems = max(0, $totalItems);
        $totalPages = max(1, (int)ceil($totalItems / $perPage));
        $page = max(1, min($totalPages, (int)Request::get($pageParam, 1)));
        $offset = ($page - 1) * $perPage;

        return [
            'page'           => $page,
            'per_page'       => $perPage,
            'total_items'    => $totalItems,
            'total_pages'    => $totalPages,
            'offset'         => $offset,
            'page_param'     => $pageParam,
            'per_page_param' => $perPageParam,
        ];
    }
}
