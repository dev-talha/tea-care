<?php
use App\Core\Session;
$title = __('staff_dashboard');
$csrf = Session::csrfToken();

$buildTabUrl = function (string $targetTab) use ($filterRoomId, $filterDeptId): string {
    $q = ['tab' => $targetTab];
    if ($filterRoomId) {
        $q['room_id'] = $filterRoomId;
    }
    if ($filterDeptId) {
        $q['dept_id'] = $filterDeptId;
    }
    return '/staff?' . http_build_query($q);
};
$hasFilters = ($filterRoomId !== null || $filterDeptId !== null);
$morningLabel = format_time_12($morningData['slot']['service_time'] ?? '09:45:00');
$afternoonLabel = format_time_12($afternoonData['slot']['service_time'] ?? '15:30:00');
$isHomeTab = ($tab === 'home');

$renderCupThumb = static function (string $imgUrl, string $name, string $empId, string $dept, string $room, string $drink = ''): void {
    ?>
    <button
        type="button"
        data-cup-preview="1"
        data-cup-src="<?= e($imgUrl) ?>"
        data-cup-name="<?= e($name) ?>"
        data-cup-empid="<?= e($empId) ?>"
        data-cup-dept="<?= e($dept) ?>"
        data-cup-room="<?= e($room) ?>"
        data-cup-drink="<?= e($drink) ?>"
        title="Tap to enlarge cup photo"
        class="relative group w-12 h-12 sm:w-14 sm:h-14 rounded-xl overflow-hidden border-2 border-stone-200 hover:border-tea-500 active:scale-95 transition bg-tea-50 shrink-0 cursor-zoom-in focus:outline-none focus:ring-2 focus:ring-tea-500/40"
    >
        <img src="<?= e($imgUrl) ?>" alt="Cup" class="w-full h-full object-cover">
        <span class="absolute bottom-0.5 right-0.5 w-4 h-4 rounded-md bg-slate-900/75 text-white flex items-center justify-center pointer-events-none">
            <i class="ti ti-zoom-in text-[10px]"></i>
        </span>
    </button>
    <?php
};

$renderOrderActions = static function (array $o, ?int $filterRoomId, ?int $filterDeptId): void {
    $st = (string)$o['status'];
    $id = (int)$o['id'];
    $estMins = !empty($o['estimated_minutes']) ? (int)$o['estimated_minutes'] : 5;
    $csrfField = Session::csrfField();
    $hiddenFilters = '<input type="hidden" name="order_id" value="' . $id . '">'
        . '<input type="hidden" name="tab" value="urgent">'
        . '<input type="hidden" name="filter_room_id" value="' . e((string)$filterRoomId) . '">'
        . '<input type="hidden" name="filter_dept_id" value="' . e((string)$filterDeptId) . '">';
    ?>
    <?php if ($st === 'pending'): ?>
        <div class="flex flex-wrap sm:flex-nowrap items-center gap-2">
            <form method="post" action="/staff/status" class="flex-1 min-w-[140px]" data-ajax-action="1">
                <?= $csrfField . $hiddenFilters ?>
                <input type="hidden" name="status" value="preparing">
                <button type="submit" class="btn-amber w-full text-sm inline-flex items-center justify-center gap-1.5">
                    <i class="ti ti-thumb-up text-base"></i>
                    <span>Accept &amp; Prepare</span>
                </button>
            </form>
            <form method="post" action="/staff/status" class="shrink-0" data-ajax-action="1">
                <?= $csrfField . $hiddenFilters ?>
                <input type="hidden" name="status" value="delivered">
                <button type="submit" class="btn-primary text-xs px-3 inline-flex items-center gap-1">
                    <i class="ti ti-check"></i>Serve
                </button>
            </form>
        </div>
    <?php elseif (in_array($st, ['accepted', 'preparing'], true)): ?>
        <div class="space-y-2">
            <form method="post" action="/staff/status" class="flex items-center gap-2" data-ajax-action="1">
                <?= $csrfField . $hiddenFilters ?>
                <input type="hidden" name="status" value="almost_done">
                <select name="estimated_minutes" aria-label="Estimated minutes" class="input-field text-xs py-2 px-2.5 w-24 shrink-0 font-semibold">
                    <?php for ($m = 1; $m <= 30; $m++): ?>
                        <option value="<?= $m ?>" <?= $m === $estMins ? 'selected' : '' ?>><?= $m ?> min</option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="flex-1 px-3 py-2.5 rounded-xl bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs sm:text-sm shadow-sm inline-flex items-center justify-center gap-1.5 whitespace-nowrap transition">
                    <i class="ti ti-hourglass-high text-base shrink-0"></i>
                    <span>Almost Done</span>
                </button>
            </form>
            <form method="post" action="/staff/status" data-ajax-action="1">
                <?= $csrfField . $hiddenFilters ?>
                <input type="hidden" name="status" value="delivered">
                <button type="submit" class="btn-primary w-full text-sm inline-flex items-center justify-center gap-1.5">
                    <i class="ti ti-circle-check text-base"></i>
                    <span><?= e(__('mark_delivered')) ?></span>
                </button>
            </form>
        </div>
    <?php elseif ($st === 'almost_done'): ?>
        <div class="space-y-2">
            <div class="rounded-xl bg-teal-50 border border-teal-200 px-3 py-1.5 text-xs font-semibold text-teal-800 flex items-center justify-between gap-2">
                <span class="inline-flex items-center gap-1.5">
                    <i class="ti ti-hourglass-high text-sm"></i>
                    <span>Almost Done (~<?= $estMins ?> min)</span>
                </span>
            </div>
            <form method="post" action="/staff/status" data-ajax-action="1">
                <?= $csrfField . $hiddenFilters ?>
                <input type="hidden" name="status" value="delivered">
                <button type="submit" class="btn-primary w-full text-sm inline-flex items-center justify-center gap-1.5">
                    <i class="ti ti-circle-check text-base"></i>
                    <span><?= e(__('mark_delivered')) ?></span>
                </button>
            </form>
        </div>
    <?php endif; ?>
    <?php
};
?>

<div id="staff-live-top" class="space-y-4 md:space-y-6">
<!-- 4 Prominent Counters (Mobile: only on Home tab; Desktop: always shown) -->
<section class="<?= $isHomeTab ? 'grid' : 'hidden md:grid' ?> grid-cols-2 sm:grid-cols-4 gap-2.5 md:gap-4">
    <div class="card p-3 md:p-4 text-center border-amberwarm-400/50 bg-amberwarm-50">
        <div id="cnt-pending" class="text-2xl md:text-3xl font-extrabold text-amberwarm-600"><?= (int)($counters['pending_cnt'] ?? 0) ?></div>
        <div class="text-[11px] md:text-xs font-bold text-slate-600 leading-tight mt-0.5"><?= e(__('pending_count')) ?></div>
    </div>
    <div class="card p-3 md:p-4 text-center border-indigo-200 bg-indigo-50/70">
        <div id="cnt-preparing" class="text-2xl md:text-3xl font-extrabold text-indigo-700"><?= (int)($counters['preparing_cnt'] ?? 0) ?></div>
        <div class="text-[11px] md:text-xs font-bold text-slate-600 leading-tight mt-0.5"><?= e(__('preparing_count')) ?></div>
    </div>
    <div class="card p-3 md:p-4 text-center border-emerald-200 bg-emerald-50/70">
        <div id="cnt-delivered" class="text-2xl md:text-3xl font-extrabold text-emerald-700"><?= (int)($counters['delivered_cnt'] ?? 0) ?></div>
        <div class="text-[11px] md:text-xs font-bold text-slate-600 leading-tight mt-0.5"><?= e(__('delivered_today_count')) ?></div>
    </div>
    <a href="<?= e($buildTabUrl('skipped')) ?>" class="card p-3 md:p-4 text-center border-rose-200 bg-rose-50/70 block">
        <div id="cnt-skipped" class="text-2xl md:text-3xl font-extrabold text-rose-600"><?= (int)$totalSkippedAll ?></div>
        <div class="text-[11px] md:text-xs font-bold text-slate-600 leading-tight mt-0.5">Skipped Today</div>
    </a>
</section>

<?php if ($isHomeTab): ?>
<!-- Mobile Home: Today's Morning & Afternoon Cup Summary (Summary only, no lists) -->
<section class="md:hidden grid grid-cols-2 gap-2.5">
    <?php foreach (['morning' => ['label' => $morningLabel, 'title' => 'Morning', 'data' => $morningData], 'afternoon' => ['label' => $afternoonLabel, 'title' => 'Afternoon', 'data' => $afternoonData]] as $sKey => $sInfo):
        $sSum = $sInfo['data']['summary'];
    ?>
        <a href="<?= e($buildTabUrl($sKey)) ?>" class="card p-3.5 space-y-2 block">
            <div class="flex items-center justify-between gap-1">
                <span class="text-xs font-bold text-slate-800"><?= e($sInfo['title']) ?></span>
                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-stone-100 text-slate-600"><?= e($sInfo['label']) ?></span>
            </div>
            <div class="flex items-center justify-between text-xs font-bold">
                <span class="inline-flex items-center gap-1 text-tea-800"><i class="ti ti-cup"></i><?= (int)$sSum['tea'] ?> Tea</span>
                <span class="inline-flex items-center gap-1 text-amberwarm-600"><i class="ti ti-coffee"></i><?= (int)$sSum['coffee'] ?> Cof</span>
            </div>
            <div class="text-[11px] text-slate-500 flex items-center justify-between">
                <span><?= $sInfo['data']['is_finalized'] ? 'Final' : 'Estimate' ?></span>
                <span class="font-semibold text-tea-700"><?= (int)$sSum['pending'] ?> pend</span>
            </div>
        </a>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<!-- Urgent Pending Banner (Mobile: only on Home tab; Desktop: always when non-urgent tab) -->
<?php if ($urgentTotalCount > 0 && $tab !== 'urgent'): ?>
    <a href="<?= e($buildTabUrl('urgent')) ?>" style="text-decoration:none" class="<?= $isHomeTab ? 'flex' : 'hidden md:flex' ?> rounded-2xl bg-amberwarm-500 text-white p-3.5 items-center justify-between gap-2 shadow-sm hover:bg-amberwarm-600 transition">
        <span class="text-xs md:text-sm font-bold inline-flex items-center gap-1.5 min-w-0">
            <i class="ti ti-bolt text-base shrink-0"></i>
            <span class="truncate"><?= (int)$urgentTotalCount ?> Urgent / Guest Order(s) waiting</span>
        </span>
        <span class="text-xs md:text-sm font-extrabold inline-flex items-center gap-1 shrink-0">
            <span>View Queue</span>
            <i class="ti ti-arrow-right"></i>
        </span>
    </a>
<?php endif; ?>

<!-- Unread In-App Notifications (Mobile: only on Home tab; Desktop: always shown) -->
<?php if (!empty($unreadNotifs)): ?>
    <div class="<?= $isHomeTab ? 'block' : 'hidden md:block' ?> rounded-2xl bg-tea-800 text-white p-3.5 md:p-4 space-y-2 shadow-sm">
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-bold uppercase tracking-wider text-amberwarm-100 inline-flex items-center gap-1.5">
                <i class="ti ti-bell-ringing text-base"></i>
                <span>New Alerts (<?= count($unreadNotifs) ?>)</span>
            </span>
            <form method="post" action="/staff/notifications/read" class="shrink-0">
                <?= Session::csrfField() ?>
                <button type="submit" class="text-xs underline text-tea-200">Mark all read</button>
            </form>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-xs">
            <?php foreach (array_slice($unreadNotifs, 0, 3) as $n): ?>
                <div class="bg-tea-700/60 rounded-xl px-3 py-2 min-w-0">
                    <div class="font-bold truncate"><?= e($n['title']) ?></div>
                    <div class="text-tea-100 break-words"><?= e($n['body']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Mobile Orders Session Toggle (Morning / Afternoon) shown only on Mobile inside Orders tabs -->
<?php if ($tab === 'morning' || $tab === 'afternoon'): ?>
    <div class="md:hidden grid grid-cols-2 gap-1.5 bg-white p-1.5 rounded-2xl border border-stone-200 font-bold">
        <a href="<?= e($buildTabUrl('morning')) ?>" style="font-size:11px;" class="text-center py-2.5 px-2 rounded-xl flex items-center justify-center whitespace-nowrap <?= $tab === 'morning' ? 'bg-tea-600 text-white shadow-sm' : 'text-slate-600' ?>">
            <span>Morning (<?= e($morningLabel) ?>)</span>
        </a>
        <a href="<?= e($buildTabUrl('afternoon')) ?>" style="font-size:11px;" class="text-center py-2.5 px-2 rounded-xl flex items-center justify-center whitespace-nowrap <?= $tab === 'afternoon' ? 'bg-tea-600 text-white shadow-sm' : 'text-slate-600' ?>">
            <span>Afternoon (<?= e($afternoonLabel) ?>)</span>
        </a>
    </div>
<?php endif; ?>
</div>

<!-- Desktop Toolbar: Tab Switcher + Room & Dept Filters (Tab switcher hidden on mobile where bottom nav handles switching) -->
<div class="<?= $isHomeTab ? 'hidden md:grid' : 'grid' ?> grid-cols-1 lg:grid-cols-12 gap-3 items-center">
    <!-- Desktop Tab Switcher (Hidden on Mobile) -->
    <div id="staff-live-tabs" class="hidden md:grid lg:col-span-7 grid-cols-5 gap-1.5 bg-white p-1.5 rounded-2xl border border-stone-200 text-xs font-bold">
        <a href="<?= e($buildTabUrl('skipped')) ?>" class="text-center py-2.5 px-2 rounded-xl flex items-center justify-center gap-1.5 min-w-0 <?= ($tab === 'skipped' || $tab === 'home') ? 'bg-tea-600 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-50' ?>">
            <i class="ti ti-player-skip-forward text-sm shrink-0"></i>
            <span class="truncate">Skip (<?= (int)$skippedFilteredCount ?>)</span>
        </a>
        <a href="<?= e($buildTabUrl('morning')) ?>" class="text-center py-2.5 px-2 rounded-xl flex items-center justify-center gap-1.5 min-w-0 <?= $tab === 'morning' ? 'bg-tea-600 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-50' ?>">
            <i class="ti ti-sun text-sm shrink-0"></i>
            <span class="truncate"><?= e($morningLabel) ?></span>
        </a>
        <a href="<?= e($buildTabUrl('afternoon')) ?>" class="text-center py-2.5 px-2 rounded-xl flex items-center justify-center gap-1.5 min-w-0 <?= $tab === 'afternoon' ? 'bg-tea-600 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-50' ?>">
            <i class="ti ti-clock text-sm shrink-0"></i>
            <span class="truncate"><?= e($afternoonLabel) ?></span>
        </a>
        <a href="<?= e($buildTabUrl('urgent')) ?>" class="text-center py-2.5 px-2 rounded-xl flex items-center justify-center gap-1.5 min-w-0 <?= $tab === 'urgent' ? 'bg-tea-600 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-50' ?>">
            <i class="ti ti-bolt text-sm shrink-0"></i>
            <span class="truncate">Urgent (<?= $urgentTotalCount ?>)</span>
        </a>
        <a href="<?= e($buildTabUrl('completed')) ?>" class="text-center py-2.5 px-2 rounded-xl flex items-center justify-center gap-1.5 min-w-0 <?= $tab === 'completed' ? 'bg-tea-600 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-50' ?>">
            <i class="ti ti-circle-check text-sm shrink-0"></i>
            <span class="truncate">Done</span>
        </a>
    </div>

    <!-- Room & Department Filter Bar -->
    <form method="get" action="/staff" class="lg:col-span-5 card p-2.5 grid grid-cols-2 sm:flex sm:items-center gap-2">
        <input type="hidden" name="tab" value="<?= e($tab === 'home' ? 'skipped' : $tab) ?>">
        <i class="ti ti-filter text-base text-tea-700 hidden sm:inline shrink-0 ml-1"></i>
        <select name="room_id" onchange="this.form.submit()" class="input-field text-xs py-2 w-full min-w-0 sm:flex-1">
            <option value="">All Rooms</option>
            <?php foreach ($roomsList as $rm): ?>
                <option value="<?= (int)$rm['id'] ?>" <?= $filterRoomId === (int)$rm['id'] ? 'selected' : '' ?>><?= e($rm['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="dept_id" onchange="this.form.submit()" class="input-field text-xs py-2 w-full min-w-0 sm:flex-1">
            <option value="">All Depts</option>
            <?php foreach ($deptsList as $dp): ?>
                <option value="<?= (int)$dp['id'] ?>" <?= $filterDeptId === (int)$dp['id'] ? 'selected' : '' ?>><?= e($dp['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($hasFilters): ?>
            <a href="/staff?tab=<?= e($tab === 'home' ? 'skipped' : $tab) ?>" class="col-span-2 sm:col-span-1 text-center text-rose-600 text-xs font-bold underline px-2 py-1 shrink-0">Clear Filters</a>
        <?php endif; ?>
    </form>
</div>

<div id="staff-live-panel" data-staff-live="1" class="space-y-4 md:space-y-6">
<?php if ($tab === 'home' || $tab === 'skipped'): ?>
    <!-- Skip List (Hidden on mobile when on 'home' summary tab; shown on Desktop & Mobile 'skipped' tab) -->
    <section class="<?= $isHomeTab ? 'hidden md:block' : 'block' ?> space-y-3">
        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-600 inline-flex items-center gap-1.5">
            <i class="ti ti-player-skip-forward text-base text-rose-600"></i>
            <span>Today's Skip List (<?= (int)$skippedFilteredCount ?>)</span>
        </h2>

        <?php if (empty($skippedList)): ?>
            <div class="card p-8 text-center text-sm text-slate-500 space-y-1">
                <i class="ti ti-circle-check text-3xl text-emerald-600 block"></i>
                <div>No employees have skipped today<?= $hasFilters ? ' for the selected filter' : '' ?>.</div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                <?php foreach ($skippedList as $sk):
                    $sessionLabel = match ($sk['session']) {
                        'all'       => 'Both Sessions',
                        'morning'   => "Morning ({$morningLabel})",
                        'afternoon' => "Afternoon ({$afternoonLabel})",
                        default     => ucfirst($sk['session']),
                    };
                    $reasonLabel = match ($sk['reason']) {
                        'paused'           => 'Paused / Away',
                        'skipped_today'    => 'Skipped Today',
                        'disabled_default' => 'Disabled in Defaults',
                        default            => 'Skipped',
                    };
                    $cupUrl = cup_image_url($sk['cup_image'] ?? null, (int)$sk['user_id']);
                ?>
                <div class="card p-3.5 flex items-start sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <?php $renderCupThumb($cupUrl, (string)$sk['full_name'], (string)($sk['employee_id'] ?? ''), (string)$sk['department_name'], (string)$sk['room_name'], 'Skipped: ' . $sessionLabel); ?>
                        <div class="min-w-0 flex-1">
                            <div class="font-bold text-sm text-slate-800 truncate"><?= e($sk['full_name']) ?></div>
                            <div class="hidden sm:flex text-xs text-slate-500 truncate items-center gap-1">
                                <i class="ti ti-door text-slate-400 shrink-0"></i>
                                <strong class="truncate"><?= e($sk['room_name']) ?></strong>
                                <span>&bull;</span>
                                <span class="truncate"><?= e($sk['department_name']) ?></span>
                            </div>
                            <div class="text-[11px] font-semibold text-rose-600 mt-0.5 inline-flex items-center gap-1">
                                <i class="ti ti-player-skip-forward text-xs"></i>
                                <span><?= e($sessionLabel) ?></span>
                            </div>
                        </div>
                    </div>
                    <span class="px-2 py-1 rounded-lg bg-rose-50 text-rose-700 border border-rose-200 text-[10px] sm:text-[11px] font-bold shrink-0">
                        <?= e($reasonLabel) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>

            <?php
            $pgPage = (int)$skipPage;
            $pgTotalPages = (int)$skipTotalPages;
            $pgTotalItems = (int)$skippedFilteredCount;
            $pgBaseUrl = '/staff';
            $pgQuery = ['tab' => 'skipped', 'room_id' => $filterRoomId, 'dept_id' => $filterDeptId];
            require __DIR__ . '/../partials/pagination.php';
            ?>
        <?php endif; ?>
    </section>

<?php elseif ($tab === 'urgent'): ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
        <!-- A. Guest & Meeting Orders (First on both Desktop & Mobile) -->
        <section class="space-y-3 min-w-0">
            <h2 class="text-sm font-bold uppercase tracking-wide text-amberwarm-600 inline-flex items-center gap-1.5">
                <i class="ti ti-users text-base"></i>
                <span><?= e(__('guest_requests')) ?> (<?= count($guestOrders) ?>)</span>
            </h2>

            <?php if (empty($guestOrders)): ?>
                <div class="card p-6 text-center text-sm text-slate-500">No pending guest or meeting orders<?= $hasFilters ? ' for this filter' : '' ?>.</div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($guestOrders as $o):
                        $totalCups = array_sum(array_map(fn($x) => (int)$x['quantity'], $o['items']));
                    ?>
                    <div class="card p-4 space-y-3 border-2 border-amberwarm-400/60 bg-amberwarm-50/40">
                        <div class="space-y-1">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex flex-wrap items-center gap-1.5 min-w-0 flex-1">
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-amberwarm-500 text-white text-[10px] font-extrabold uppercase">
                                        Guest / Meeting &bull; <?= $totalCups ?> Cups
                                    </span>
                                    <?php if (!empty($o['requested_within'])): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-sky-50 text-sky-700 border border-sky-200 text-[10px] font-bold">
                                            <i class="ti ti-clock-bolt text-xs"></i><?= e($o['requested_within']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="shrink-0 flex flex-col items-end gap-1">
                                    <?php $status = $o['status']; $estimatedMinutes = $o['estimated_minutes'] ?? null; require __DIR__ . '/../partials/status-badge.php'; ?>
                                    <span class="text-[11px] font-medium text-slate-500 inline-flex items-center gap-1">
                                        <i class="ti ti-clock"></i><?= e(date('g:i A', strtotime($o['created_at']))) ?>
                                    </span>
                                </div>
                            </div>
                            <h3 class="font-bold text-base text-slate-800 flex items-center gap-1">
                                <i class="ti ti-map-pin text-amberwarm-600 shrink-0"></i>
                                <span class="truncate"><?= e($o['room_name_snapshot']) ?></span>
                            </h3>
                            <div class="text-xs text-slate-600 break-words">
                                Requested by <strong><?= e($o['employee_name_snapshot']) ?></strong>
                                <?= !empty($o['meeting_title']) ? ' &bull; ' . e($o['meeting_title']) : '' ?>
                                <?= !empty($o['guest_count']) ? ' (' . (int)$o['guest_count'] . ' guests)' : '' ?>
                            </div>
                        </div>

                        <div class="rounded-xl bg-white border border-amberwarm-400/30 p-3 space-y-1">
                            <?php foreach ($o['items'] as $it): ?>
                                <div class="flex items-center justify-between gap-2 text-sm font-semibold text-slate-800">
                                    <span class="inline-flex items-center gap-1.5 min-w-0">
                                        <i class="ti <?= $it['beverage'] === 'coffee' ? 'ti-coffee text-amberwarm-600' : 'ti-cup text-tea-700' ?> shrink-0"></i>
                                        <span class="truncate"><?= e(badge_beverage($it['beverage'])) ?> — <?= e(badge_sugar($it['sugar'])) ?></span>
                                    </span>
                                    <span class="px-2 py-0.5 rounded-lg bg-cream-100 font-bold shrink-0"><?= (int)$it['quantity'] ?>&times;</span>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!empty($o['note'])): ?>
                                <div class="text-xs text-slate-600 pt-1 border-t border-stone-100 mt-1 flex items-start gap-1 break-words">
                                    <i class="ti ti-notes text-slate-400 mt-0.5 shrink-0"></i>
                                    <span>"<?= e($o['note']) ?>"</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php $renderOrderActions($o, $filterRoomId, $filterDeptId); ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- B. Urgent On-Demand Requests -->
        <section class="space-y-3 min-w-0">
            <h2 class="text-sm font-bold uppercase tracking-wide text-slate-600 inline-flex items-center gap-1.5">
                <i class="ti ti-bolt text-base text-amberwarm-600"></i>
                <span><?= e(__('urgent_requests')) ?> (<?= count($urgentOrders) ?>)</span>
            </h2>

            <?php if (empty($urgentOrders)): ?>
                <div class="card p-6 text-center text-sm text-slate-500">No active on-demand requests right now<?= $hasFilters ? ' for this filter' : '' ?>.</div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($urgentOrders as $o):
                        $isHi = $highlight === (int)$o['id'];
                        $cupUrl = cup_image_url($o['cup_image_snapshot'] ?? null, (int)$o['user_id']);
                        $drinkSummary = implode(', ', array_map(
                            fn($it) => (int)$it['quantity'] . 'x ' . badge_beverage($it['beverage']) . ' (' . badge_sugar($it['sugar']) . ')',
                            $o['items']
                        ));
                    ?>
                    <div class="card p-4 space-y-3 <?= $isHi ? 'ring-2 ring-amberwarm-500' : '' ?>">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <?php $renderCupThumb($cupUrl, (string)$o['employee_name_snapshot'], (string)($o['employee_id_snapshot'] ?? ''), (string)($o['department_name_snapshot'] ?? ''), (string)($o['room_name_snapshot'] ?? ''), $drinkSummary); ?>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <?php if ($o['status'] === 'pending'): ?>
                                            <span class="px-2 py-0.5 rounded-md bg-teal-600 text-white text-[10px] font-extrabold uppercase">New</span>
                                        <?php endif; ?>
                                        <span class="font-bold text-slate-800 truncate"><?= e($o['employee_name_snapshot']) ?></span>
                                    </div>
                                    <div class="hidden sm:flex text-xs text-slate-500 truncate items-center gap-1">
                                        <i class="ti ti-map-pin text-slate-400 shrink-0"></i>
                                        <strong class="truncate"><?= e($o['room_name_snapshot']) ?></strong> &bull; <span class="truncate"><?= e($o['department_name_snapshot']) ?></span>
                                    </div>
                                    <div class="text-[11px] text-slate-400 inline-flex items-center gap-1">
                                        <i class="ti ti-clock"></i><?= e(date('g:i A', strtotime($o['created_at']))) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="shrink-0">
                                <?php $status = $o['status']; $estimatedMinutes = $o['estimated_minutes'] ?? null; require __DIR__ . '/../partials/status-badge.php'; ?>
                            </div>
                        </div>

                        <div class="rounded-xl bg-cream-50 border border-stone-200 p-3">
                            <?php foreach ($o['items'] as $it): ?>
                                <div class="font-bold text-base text-slate-800 flex flex-wrap items-center gap-1.5">
                                    <i class="ti <?= $it['beverage'] === 'coffee' ? 'ti-coffee text-amberwarm-600' : 'ti-cup text-tea-700' ?> text-lg"></i>
                                    <span><?= (int)$it['quantity'] ?> &times; <?= e(badge_beverage($it['beverage'])) ?></span>
                                    <span class="text-xs font-semibold text-tea-700">&bull; <?= e(badge_sugar($it['sugar'])) ?></span>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!empty($o['note'])): ?>
                                <div class="text-xs text-slate-600 mt-1 flex items-start gap-1 break-words">
                                    <i class="ti ti-notes text-slate-400 mt-0.5 shrink-0"></i>
                                    <span>"<?= e($o['note']) ?>"</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php $renderOrderActions($o, $filterRoomId, $filterDeptId); ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

<?php elseif ($tab === 'morning' || $tab === 'afternoon'):
    $sData = $tab === 'morning' ? $morningData : $afternoonData;
    $sum = $sData['summary'];
    $rooms = $sData['rooms'];
?>
    <!-- Session Summary -->
    <div class="card p-4 md:p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="min-w-0">
            <h2 class="font-bold text-base md:text-lg text-slate-800"><?= e($sData['slot']['label_en'] ?? ucfirst($tab)) ?> (<?= e(format_time_12($sData['slot']['service_time'] ?? '')) ?>)</h2>
            <div class="flex items-center flex-nowrap gap-1 sm:gap-2 overflow-x-auto text-[10px] sm:text-xs font-semibold text-slate-600 mt-2 whitespace-nowrap">
                <span class="px-1.5 sm:px-2.5 py-1 rounded-lg bg-cream-50 border border-stone-200 shrink-0">Normal: <?= (int)$sum['sugar_normal'] ?></span>
                <span class="px-1.5 sm:px-2.5 py-1 rounded-lg bg-cream-50 border border-stone-200 shrink-0">Less: <?= (int)$sum['sugar_less'] ?></span>
                <span class="px-1.5 sm:px-2.5 py-1 rounded-lg bg-cream-50 border border-stone-200 shrink-0">No Sugar: <?= (int)$sum['sugar_none'] ?></span>
                <span class="px-1.5 sm:px-2.5 py-1 rounded-lg bg-tea-50 text-tea-700 font-bold shrink-0">Pending: <?= (int)$sum['pending'] ?> | Done: <?= (int)$sum['delivered'] ?></span>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 shrink-0 md:min-w-[260px]">
            <div class="rounded-xl bg-tea-50 border border-tea-200 p-3 text-center">
                <div class="text-2xl font-extrabold text-tea-800 inline-flex items-center justify-center gap-1.5">
                    <i class="ti ti-cup"></i><?= (int)$sum['tea'] ?>
                </div>
                <div class="text-xs font-semibold text-tea-700">Total Tea Cups</div>
            </div>
            <div class="rounded-xl bg-amberwarm-50 border border-amberwarm-400/40 p-3 text-center">
                <div class="text-2xl font-extrabold text-amberwarm-600 inline-flex items-center justify-center gap-1.5">
                    <i class="ti ti-coffee"></i><?= (int)$sum['coffee'] ?>
                </div>
                <div class="text-xs font-semibold text-amberwarm-600">Total Coffee Cups</div>
            </div>
        </div>
    </div>

    <?php if (empty($rooms)): ?>
        <div class="card p-8 text-center text-sm text-slate-500">No orders scheduled for this session<?= $hasFilters ? ' matching your filter' : '' ?>.</div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 items-start">
            <?php foreach ($rooms as $room):
                $unfinished = array_values(array_filter($room['orders'], fn($o) => $o['id'] !== null && in_array($o['status'], ['pending', 'accepted', 'preparing', 'almost_done'], true)));
            ?>
            <div class="card p-4 space-y-3 min-w-0">
                <div class="flex items-center justify-between gap-2 pb-2 border-b border-stone-100">
                    <h3 class="flex-1 min-w-0 font-bold text-sm text-tea-800 inline-flex items-center gap-1.5">
                        <i class="ti ti-door text-base shrink-0"></i>
                        <span class="truncate"><?= e($room['room_name']) ?> (<?= count($room['orders']) ?>)</span>
                    </h3>
                    <?php if ($sData['is_finalized'] && count($unfinished) > 0): ?>
                        <form method="post" action="/staff/deliver-room" data-ajax-action="1" onsubmit="return confirm('Mark <?= count($unfinished) ?> order(s) in <?= e($room['room_name']) ?> as delivered?');" class="shrink-0">
                            <?= Session::csrfField() ?>
                            <input type="hidden" name="room_id" value="<?= (int)$room['room_id'] ?>">
                            <input type="hidden" name="slot_key" value="<?= e($tab) ?>">
                            <input type="hidden" name="filter_room_id" value="<?= e((string)$filterRoomId) ?>">
                            <input type="hidden" name="filter_dept_id" value="<?= e((string)$filterDeptId) ?>">
                            <?php foreach ($unfinished as $uo): ?>
                                <input type="hidden" name="order_ids[]" value="<?= (int)$uo['id'] ?>">
                            <?php endforeach; ?>
                            <button type="submit" style="padding: 0.375rem 0.625rem;" class="rounded-lg bg-tea-600 text-white text-xs font-bold inline-flex items-center gap-1 whitespace-nowrap">
                                <i class="ti ti-circle-check"></i><?php if ((int)$room['room_id'] === 0): ?>Deliver All (<?= count($unfinished) ?>)<?php else: ?>Deliver <span class="hidden sm:inline">Room </span>(<?= count($unfinished) ?>)<?php endif; ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="divide-y divide-stone-100">
                    <?php foreach ($room['orders'] as $o):
                        $cupUrl = cup_image_url($o['cup_image_snapshot'] ?? null, (int)$o['user_id']);
                        $drinkSummary = (int)$o['quantity'] . 'x ' . badge_beverage($o['beverage']) . ' • ' . badge_sugar($o['sugar']) . (!empty($o['note']) ? ' ("' . $o['note'] . '")' : '');
                    ?>
                        <div class="py-2.5 flex items-center justify-between gap-2.5">
                            <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                <?php $renderCupThumb($cupUrl, (string)$o['employee_name_snapshot'], (string)($o['employee_id_snapshot'] ?? ''), (string)($o['department_name_snapshot'] ?? ''), (string)$room['room_name'], $drinkSummary); ?>
                                <div class="min-w-0 flex-1">
                                    <div class="font-bold text-sm text-slate-800 truncate"><?= e($o['employee_name_snapshot']) ?></div>
                                    <div class="hidden md:block text-[11px] text-slate-400 truncate"><?= e($o['department_name_snapshot'] ?? '') ?></div>
                                    <div class="text-xs font-semibold text-tea-700 flex items-center gap-1 flex-wrap">
                                        <i class="ti <?= $o['beverage'] === 'coffee' ? 'ti-coffee text-amberwarm-600' : 'ti-cup text-tea-700' ?> shrink-0"></i>
                                        <span><?= (int)$o['quantity'] ?>&times; <?= e(badge_beverage($o['beverage'])) ?> &bull; <?= e(badge_sugar($o['sugar'])) ?></span>
                                    </div>
                                    <?php if (!empty($o['note'])): ?>
                                        <div class="text-[11px] text-slate-500 break-words">"<?= e($o['note']) ?>"</div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="shrink-0">
                                <?php if ($o['id'] === null): ?>
                                    <span class="px-2 py-1 rounded-lg bg-stone-100 text-stone-500 text-[11px] font-semibold">Est.</span>
                                <?php elseif ($o['status'] === 'delivered'): ?>
                                    <span class="px-2.5 py-1 rounded-lg bg-emerald-100 text-emerald-700 text-xs font-bold inline-flex items-center gap-1">
                                        <i class="ti ti-check"></i>Done
                                    </span>
                                <?php elseif ($o['status'] === 'system_cancelled'): ?>
                                    <span class="px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-300 text-slate-700 text-xs font-bold whitespace-nowrap">Auto Cancel</span>
                                <?php elseif ($o['status'] === 'cancelled'): ?>
                                    <span class="px-2.5 py-1 rounded-lg bg-rose-100 text-rose-700 text-xs font-bold">Cancelled</span>
                                <?php else: ?>
                                    <form method="post" action="/staff/status" data-ajax-action="1">
                                        <?= Session::csrfField() ?>
                                        <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                                        <input type="hidden" name="status" value="delivered">
                                        <input type="hidden" name="tab" value="<?= e($tab) ?>">
                                        <input type="hidden" name="filter_room_id" value="<?= e((string)$filterRoomId) ?>">
                                        <input type="hidden" name="filter_dept_id" value="<?= e((string)$filterDeptId) ?>">
                                        <button type="submit" class="btn-primary text-xs py-2 px-3 min-h-[40px]">Deliver</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php elseif ($tab === 'completed'): ?>
    <section class="space-y-3">
        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-600 inline-flex items-center gap-1.5">
            <i class="ti ti-circle-check text-base text-emerald-600"></i>
            <span><?= e(__('completed_today')) ?> (<?= (int)($completedTotalCount ?? count($completedOrders)) ?>)</span>
        </h2>
        <?php if (empty($completedOrders)): ?>
            <div class="card p-6 text-center text-sm text-slate-500">No completed orders yet today<?= $hasFilters ? ' for this filter' : '' ?>.</div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                <?php foreach ($completedOrders as $o):
                    $cupUrl = cup_image_url($o['cup_image_snapshot'] ?? null, (int)($o['user_id'] ?? 0));
                    $drinkSummary = implode(', ', array_map(
                        fn($it) => (int)$it['quantity'] . 'x ' . badge_beverage($it['beverage']) . ' (' . badge_sugar($it['sugar']) . ')',
                        $o['items']
                    ));
                ?>
                    <div class="card p-3.5 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <?php if (!empty($o['user_id'])): ?>
                                <?php $renderCupThumb($cupUrl, (string)$o['employee_name_snapshot'], (string)($o['employee_id_snapshot'] ?? ''), (string)($o['department_name_snapshot'] ?? ''), (string)$o['room_name_snapshot'], $drinkSummary); ?>
                            <?php endif; ?>
                            <div class="min-w-0 flex-1">
                                <div class="font-bold text-sm text-slate-800 truncate">
                                    <?= e($o['employee_name_snapshot']) ?><span class="hidden sm:inline"> &bull; <?= e($o['room_name_snapshot']) ?></span>
                                </div>
                                <div class="text-xs text-slate-600 break-words">
                                    <?= e($drinkSummary) ?>
                                </div>
                                <div class="text-[11px] text-slate-400">
                                    <?= e(strtoupper($o['order_type'])) ?> &bull; <?= e(date('g:i A', strtotime($o['updated_at']))) ?>
                                </div>
                            </div>
                        </div>
                        <div class="shrink-0">
                            <?php $status = $o['status']; $estimatedMinutes = $o['estimated_minutes'] ?? null; require __DIR__ . '/../partials/status-badge.php'; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php
            $pgPage = (int)($completedPage ?? 1);
            $pgTotalPages = (int)($completedTotalPages ?? 1);
            $pgTotalItems = (int)($completedTotalCount ?? count($completedOrders));
            $pgBaseUrl = '/staff';
            $pgQuery = ['tab' => 'completed', 'room_id' => $filterRoomId, 'dept_id' => $filterDeptId];
            require __DIR__ . '/../partials/pagination.php';
            ?>
        <?php endif; ?>
    </section>
<?php endif; ?>
</div>
