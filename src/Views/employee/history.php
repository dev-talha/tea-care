<?php
$title = __('history');
$filters = [
    'today' => ['short' => 'Today',   'full' => __('filter_today')],
    '7d'    => ['short' => '7 Days',  'full' => __('filter_7d')],
    '30d'   => ['short' => '30 Days', 'full' => __('filter_30d')],
    'older' => ['short' => 'Older',   'full' => __('filter_older')],
];
?>
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
    <h2 class="text-xl font-bold text-slate-800"><?= e(__('history')) ?></h2>
    <!-- Filter Pills (4-col compact fit on mobile, inline pills on desktop) -->
    <div class="grid grid-cols-4 gap-1.5 sm:flex sm:flex-wrap sm:gap-2">
        <?php foreach ($filters as $fk => $fmeta): ?>
            <a href="/history?filter=<?= e($fk) ?>" class="px-1.5 sm:px-3.5 py-2 rounded-xl text-xs font-semibold text-center whitespace-nowrap border transition <?= $filter === $fk ? 'bg-tea-600 text-white border-tea-600 shadow-sm' : 'bg-white text-slate-700 border-tea-200/70 hover:bg-tea-50' ?>">
                <span class="sm:hidden"><?= e($fmeta['short']) ?></span>
                <span class="hidden sm:inline"><?= e($fmeta['full']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Requested vs Delivered Summary -->
<div class="grid grid-cols-3 gap-2.5 md:gap-4">
    <div class="rounded-2xl border border-sky-200/80 bg-gradient-to-br from-white to-sky-50/90 p-3 md:p-4 text-center shadow-sm">
        <div class="text-xl md:text-2xl font-extrabold text-sky-900"><?= (int)($summary['requested_cups'] ?? 0) ?></div>
        <div class="text-xs font-semibold text-sky-700 mt-0.5 whitespace-nowrap">Requested<span class="hidden sm:inline"> Cups</span></div>
    </div>
    <div class="rounded-2xl border border-emerald-200/80 bg-gradient-to-br from-white to-emerald-50/90 p-3 md:p-4 text-center shadow-sm">
        <div class="text-xl md:text-2xl font-extrabold text-emerald-700"><?= (int)($summary['delivered_cups'] ?? 0) ?></div>
        <div class="text-xs font-semibold text-emerald-700 mt-0.5 whitespace-nowrap">Delivered<span class="hidden sm:inline"> Cups</span></div>
    </div>
    <div class="rounded-2xl border border-rose-200/80 bg-gradient-to-br from-white to-rose-50/90 p-3 md:p-4 text-center shadow-sm">
        <div class="text-xl md:text-2xl font-extrabold text-rose-600"><?= (int)($summary['cancelled_cups'] ?? 0) ?></div>
        <div class="text-xs font-semibold text-rose-600 mt-0.5 whitespace-nowrap">Cancelled</div>
    </div>
</div>

<!-- Orders List -->
<?php if (empty($orders)): ?>
    <div class="card p-8 text-center text-sm text-slate-500">
        No order history found for this period.
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3.5">
        <?php foreach ($orders as $o): ?>
            <div class="card p-4 space-y-2.5 flex flex-col justify-between">
                <div class="space-y-2">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1 text-xs font-semibold text-slate-600 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 leading-snug">
                            <span class="whitespace-nowrap"><?= e(date('d M Y', strtotime($o['order_date']))) ?></span>
                            <span class="text-slate-300">&bull;</span>
                            <?php if ($o['order_type'] === 'regular'): ?>
                                <span class="inline-flex items-center gap-1 whitespace-nowrap">
                                    <i class="ti <?= $o['slot_key'] === 'morning' ? 'ti-sun text-amberwarm-600' : 'ti-clock text-tea-600' ?> text-sm"></i>
                                    <span><?= e($o['slot_key'] === 'morning' ? __('morning') : __('afternoon')) ?></span>
                                </span>
                            <?php elseif ($o['order_type'] === 'guest'): ?>
                                <span class="inline-flex items-center gap-1 whitespace-nowrap">
                                    <i class="ti ti-users text-amberwarm-600 text-sm"></i>
                                    <span>Guest Order</span>
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 whitespace-nowrap">
                                    <i class="ti ti-bolt text-tea-700 text-sm"></i>
                                    <span>On-Demand (<?= e(date('g:i A', strtotime($o['created_at']))) ?>)</span>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php $status = $o['status']; $estimatedMinutes = $o['estimated_minutes'] ?? null; require __DIR__ . '/../partials/status-badge.php'; ?>
                    </div>

                    <div class="text-sm font-bold text-slate-800 flex flex-wrap items-center gap-2">
                        <?php foreach ($o['items'] as $idx => $it): ?>
                            <span class="inline-flex items-center gap-1">
                                <?= $idx > 0 ? ', ' : '' ?>
                                <i class="ti <?= $it['beverage'] === 'coffee' ? 'ti-coffee text-amberwarm-600' : 'ti-cup text-tea-700' ?>"></i>
                                <span><?= (int)$it['quantity'] ?>&times; <?= e(badge_beverage($it['beverage'])) ?></span>
                                <span class="text-xs font-medium text-slate-500">(<?= e(badge_sugar($it['sugar'])) ?>)</span>
                            </span>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($o['note'])): ?>
                        <div class="text-xs text-slate-600 bg-tea-50/60 border border-tea-100 rounded-lg px-2.5 py-1.5">"<?= e($o['note']) ?>"</div>
                    <?php endif; ?>
                </div>
                <div class="text-xs text-slate-500 inline-flex items-center gap-1.5 pt-1.5 border-t border-tea-100/70">
                    <i class="ti ti-map-pin text-tea-600"></i>
                    <span><?= e($o['room_name_snapshot']) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php
    $pgPage = (int)$page;
    $pgTotalPages = (int)$totalPages;
    $pgTotalItems = (int)($total ?? count($orders));
    $pgBaseUrl = '/history';
    $pgQuery = ['filter' => $filter];
    require __DIR__ . '/../partials/pagination.php';
    ?>
<?php endif; ?>
