<?php
/**
 * Reusable pagination component with per-page selector (25, 50, 100, 500).
 * Accepts $pgMeta (array from Paginator::paginate) OR individual $pg* variables.
 */
if (!empty($pgMeta) && is_array($pgMeta)) {
    $pgPage = (int)($pgMeta['page'] ?? 1);
    $pgPerPage = (int)($pgMeta['per_page'] ?? 25);
    $pgTotalItems = (int)($pgMeta['total_items'] ?? 0);
    $pgTotalPages = (int)($pgMeta['total_pages'] ?? 1);
    $pgPageParam = (string)($pgMeta['page_param'] ?? 'page');
    $pgPerPageParam = (string)($pgMeta['per_page_param'] ?? 'per_page');
    $pgShowPerPage = true;
} else {
    $pgPage = (int)($pgPage ?? 1);
    $pgPerPage = (int)($pgPerPage ?? 25);
    $pgTotalItems = (int)($pgTotalItems ?? 0);
    $pgTotalPages = (int)($pgTotalPages ?? 1);
    $pgPageParam = (string)($pgPageParam ?? 'page');
    $pgPerPageParam = (string)($pgPerPageParam ?? 'per_page');
    $pgShowPerPage = (bool)($pgShowPerPage ?? false);
}

if (!$pgShowPerPage && $pgTotalPages <= 1) {
    return;
}
if ($pgTotalItems === 0 && $pgTotalPages <= 1) {
    return;
}

$pgPerPageOptions = $pgPerPageOptions ?? [25, 50, 100, 500];
$pgQuery = is_array($pgQuery ?? null) ? $pgQuery : [];
$buildUrl = function (int $p, ?int $pp = null) use ($pgBaseUrl, $pgQuery, $pgPageParam, $pgPerPageParam, $pgPerPage, $pgShowPerPage): string {
    $q = array_filter($pgQuery, fn($v) => is_scalar($v) && $v !== '');
    $q[$pgPageParam] = $p;
    if ($pgShowPerPage) {
        $q[$pgPerPageParam] = $pp ?? $pgPerPage;
    }
    return $pgBaseUrl . '?' . http_build_query($q);
};
$startPage = max(1, $pgPage - 2);
$endPage = min($pgTotalPages, $pgPage + 2);
$fromRow = $pgTotalItems > 0 ? (($pgPage - 1) * $pgPerPage) + 1 : 0;
$toRow = min($pgTotalItems, $pgPage * $pgPerPage);
?>
<div class="flex flex-wrap items-center justify-between gap-2.5 pt-2">
    <div class="flex flex-wrap items-center gap-2.5 text-xs text-slate-500 font-medium">
        <span>
            Showing <strong class="text-slate-700"><?= $fromRow ?>&ndash;<?= $toRow ?></strong> of
            <strong class="text-slate-700"><?= $pgTotalItems ?></strong>
        </span>
        <?php if ($pgShowPerPage): ?>
            <label class="inline-flex items-center gap-1.5">
                <span class="text-slate-400">|</span>
                <span>Rows:</span>
                <select aria-label="Rows per page"
                        onchange="if(this.value)window.location.href=this.value"
                        class="rounded-lg border border-stone-200 bg-white px-2 py-1 text-xs font-semibold text-slate-700 focus:outline-none focus:border-tea-500">
                    <?php foreach ($pgPerPageOptions as $opt): ?>
                        <option value="<?= e($buildUrl(1, (int)$opt)) ?>" <?= (int)$opt === $pgPerPage ? 'selected' : '' ?>>
                            <?= (int)$opt ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
    </div>
    <?php if ($pgTotalPages > 1): ?>
        <div class="inline-flex flex-wrap items-center gap-1">
            <a href="<?= e($buildUrl(max(1, $pgPage - 1))) ?>"
               class="px-2.5 py-1.5 rounded-xl border border-stone-200 bg-white text-xs font-semibold text-slate-700 inline-flex items-center gap-1 <?= $pgPage <= 1 ? 'pointer-events-none opacity-40' : 'hover:bg-stone-50' ?>">
                <i class="ti ti-chevron-left"></i><span>Prev</span>
            </a>

            <?php if ($startPage > 1): ?>
                <a href="<?= e($buildUrl(1)) ?>" class="px-2.5 py-1.5 rounded-xl border border-stone-200 bg-white text-xs font-bold text-slate-700 hover:bg-stone-50">1</a>
                <?php if ($startPage > 2): ?>
                    <span class="px-1 text-xs text-slate-400">&hellip;</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                <a href="<?= e($buildUrl($p)) ?>"
                   class="px-2.5 py-1.5 rounded-xl border text-xs font-bold <?= $p === $pgPage ? 'bg-tea-600 text-white border-tea-600' : 'bg-white text-slate-700 border-stone-200 hover:bg-stone-50' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>

            <?php if ($endPage < $pgTotalPages): ?>
                <?php if ($endPage < $pgTotalPages - 1): ?>
                    <span class="px-1 text-xs text-slate-400">&hellip;</span>
                <?php endif; ?>
                <a href="<?= e($buildUrl($pgTotalPages)) ?>" class="px-2.5 py-1.5 rounded-xl border border-stone-200 bg-white text-xs font-bold text-slate-700 hover:bg-stone-50"><?= $pgTotalPages ?></a>
            <?php endif; ?>

            <a href="<?= e($buildUrl(min($pgTotalPages, $pgPage + 1))) ?>"
               class="px-2.5 py-1.5 rounded-xl border border-stone-200 bg-white text-xs font-semibold text-slate-700 inline-flex items-center gap-1 <?= $pgPage >= $pgTotalPages ? 'pointer-events-none opacity-40' : 'hover:bg-stone-50' ?>">
                <span>Next</span><i class="ti ti-chevron-right"></i>
            </a>
        </div>
    <?php endif; ?>
</div>
