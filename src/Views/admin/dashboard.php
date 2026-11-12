<?php
$title = __('dashboard');
$maxCup = 1;
foreach ($chartRows as $cr) {
    $maxCup = max($maxCup, (int)$cr['tea_cups'] + (int)$cr['coffee_cups']);
}
?>
<!-- Summary KPI Cards -->
<section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="card p-4">
        <div class="text-xs font-semibold text-slate-500">Active Employees</div>
        <div class="text-2xl font-extrabold text-tea-800 mt-1"><?= (int)$activeEmployees ?></div>
        <?php if ($pendingApprovals > 0): ?>
            <a href="/admin/employees?status=pending" class="text-xs font-semibold text-amberwarm-600 underline mt-1 inline-block"><?= (int)$pendingApprovals ?> pending approval</a>
        <?php endif; ?>
    </div>
    <div class="card p-4">
        <div class="text-xs font-semibold text-slate-500">Morning (<?= $morningFinalized ? 'Final' : 'Est.' ?>)</div>
        <div class="text-lg font-extrabold text-slate-800 mt-1 flex items-center gap-2 flex-wrap">
            <span class="inline-flex items-center gap-1"><i class="ti ti-cup text-tea-700"></i><?= (int)$morningSummary['tea'] ?> Tea</span>
            <span class="text-slate-300">&bull;</span>
            <span class="inline-flex items-center gap-1"><i class="ti ti-coffee text-amberwarm-600"></i><?= (int)$morningSummary['coffee'] ?> Coffee</span>
        </div>
    </div>
    <div class="card p-4">
        <div class="text-xs font-semibold text-slate-500">Afternoon (<?= $afternoonFinalized ? 'Final' : 'Est.' ?>)</div>
        <div class="text-lg font-extrabold text-slate-800 mt-1 flex items-center gap-2 flex-wrap">
            <span class="inline-flex items-center gap-1"><i class="ti ti-cup text-tea-700"></i><?= (int)$afternoonSummary['tea'] ?> Tea</span>
            <span class="text-slate-300">&bull;</span>
            <span class="inline-flex items-center gap-1"><i class="ti ti-coffee text-amberwarm-600"></i><?= (int)$afternoonSummary['coffee'] ?> Coffee</span>
        </div>
    </div>
    <div class="card p-4">
        <div class="text-xs font-semibold text-slate-500">Delivered Today</div>
        <div class="text-2xl font-extrabold text-emerald-700 mt-1"><?= (int)$deliveredToday ?> <span class="text-xs font-semibold text-slate-400">cups</span></div>
        <div class="text-xs text-slate-500 mt-1">Pending: <?= (int)$pendingOnDemand ?> On-Demand, <?= (int)$pendingGuest ?> Guest</div>
    </div>
</section>

<!-- Daily Consumption Chart + Dept/Room Summaries -->
<section class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- 7-Day Bar Chart -->
    <div class="card p-5 lg:col-span-1 space-y-4">
        <h2 class="font-bold text-base text-slate-800">Last 7 Days Consumption</h2>
        <?php if (empty($chartRows)): ?>
            <p class="text-sm text-slate-500">No orders recorded in the last 7 days.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($chartRows as $cr):
                    $teaW = round(((int)$cr['tea_cups'] / $maxCup) * 100);
                    $cofW = round(((int)$cr['coffee_cups'] / $maxCup) * 100);
                ?>
                <div>
                    <div class="flex justify-between text-xs font-semibold text-slate-600 mb-1">
                        <span><?= e(date('d M', strtotime($cr['order_date']))) ?></span>
                        <span class="inline-flex items-center gap-2">
                            <span class="inline-flex items-center gap-0.5"><i class="ti ti-cup text-tea-700"></i><?= (int)$cr['tea_cups'] ?></span>
                            <span class="text-slate-300">|</span>
                            <span class="inline-flex items-center gap-0.5"><i class="ti ti-coffee text-amberwarm-600"></i><?= (int)$cr['coffee_cups'] ?></span>
                        </span>
                    </div>
                    <div class="w-full h-3 bg-stone-100 rounded-full overflow-hidden flex">
                        <div class="bg-tea-600 h-full" style="width: <?= $teaW ?>%"></div>
                        <div class="bg-amberwarm-500 h-full" style="width: <?= $cofW ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="flex items-center gap-4 text-xs text-slate-500 pt-2">
                <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-tea-600"></span> Tea</span>
                <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-amberwarm-500"></span> Coffee</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Department Summary -->
    <div class="card p-5 space-y-3">
        <h2 class="font-bold text-base text-slate-800">Today by Department</h2>
        <?php if (empty($deptSummary)): ?>
            <p class="text-sm text-slate-500">No finalized orders yet today.</p>
        <?php else: ?>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-slate-500 border-b border-stone-200">
                        <th class="pb-2">Department</th>
                        <th class="pb-2 text-right">Req</th>
                        <th class="pb-2 text-right">Deliv</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <?php foreach ($deptSummary as $ds): ?>
                        <tr>
                            <td class="py-2 font-medium"><?= e($ds['dept_name']) ?></td>
                            <td class="py-2 text-right"><?= (int)$ds['total_cups'] ?></td>
                            <td class="py-2 text-right font-bold text-emerald-700"><?= (int)$ds['delivered_cups'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            $pgMeta = $deptPg ?? null;
            $pgBaseUrl = '/admin';
            $pgQuery = [
                'room_page'     => (int)($roomPg['page'] ?? 1),
                'room_per_page' => (int)($roomPg['per_page'] ?? 25),
            ];
            require __DIR__ . '/../partials/pagination.php';
            ?>
        <?php endif; ?>
    </div>

    <!-- Room Summary -->
    <div class="card p-5 space-y-3">
        <h2 class="font-bold text-base text-slate-800">Today by Room</h2>
        <?php if (empty($roomSummary)): ?>
            <p class="text-sm text-slate-500">No finalized orders yet today.</p>
        <?php else: ?>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-slate-500 border-b border-stone-200">
                        <th class="pb-2">Room</th>
                        <th class="pb-2 text-right">Req</th>
                        <th class="pb-2 text-right">Deliv</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <?php foreach ($roomSummary as $rs): ?>
                        <tr>
                            <td class="py-2 font-medium"><?= e($rs['room_name']) ?></td>
                            <td class="py-2 text-right"><?= (int)$rs['total_cups'] ?></td>
                            <td class="py-2 text-right font-bold text-emerald-700"><?= (int)$rs['delivered_cups'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            $pgMeta = $roomPg ?? null;
            $pgBaseUrl = '/admin';
            $pgQuery = [
                'dept_page'     => (int)($deptPg['page'] ?? 1),
                'dept_per_page' => (int)($deptPg['per_page'] ?? 25),
            ];
            require __DIR__ . '/../partials/pagination.php';
            ?>
        <?php endif; ?>
    </div>
</section>

<!-- Admin Creation & Activity Monitor -->
<section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="card p-5 space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-base text-slate-800 inline-flex items-center gap-1.5">
                <i class="ti ti-user-plus text-tea-700"></i>
                <span>Recently Added Accounts</span>
            </h2>
            <a href="/admin/employees" class="text-xs font-semibold text-tea-700 underline">View all</a>
        </div>
        <?php if (empty($recentUsers)): ?>
            <p class="text-sm text-slate-500">No users found.</p>
        <?php else: ?>
            <div class="divide-y divide-stone-100 text-xs">
                <?php foreach ($recentUsers as $ru): ?>
                    <div class="py-2 flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <span class="font-bold text-slate-800"><?= e($ru['full_name']) ?></span>
                            <span class="text-slate-500 ml-1">(<?= e($ru['employee_id']) ?> &bull; <?= e(ucfirst($ru['role'])) ?>)</span>
                        </div>
                        <span class="text-slate-500 shrink-0 font-mono text-[11px]"><?= e(date('d M Y, g:i A', strtotime($ru['created_at']))) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card p-5 space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-base text-slate-800 inline-flex items-center gap-1.5">
                <i class="ti ti-activity text-amberwarm-600"></i>
                <span>Recent System Activity</span>
            </h2>
            <a href="/admin/settings" class="text-xs font-semibold text-tea-700 underline">Audit log</a>
        </div>
        <?php if (empty($recentAuditLogs)): ?>
            <p class="text-sm text-slate-500">No recorded activity yet.</p>
        <?php else: ?>
            <div class="divide-y divide-stone-100 text-xs">
                <?php foreach ($recentAuditLogs as $al): ?>
                    <div class="py-2 flex items-center justify-between gap-2">
                        <div class="min-w-0 truncate">
                            <span class="font-bold text-slate-800"><?= e($al['actor_name']) ?></span>
                            <span class="text-tea-700 font-semibold mx-1"><?= e($al['action']) ?></span>
                            <span class="text-slate-400"><?= e($al['entity_type']) ?><?= $al['entity_id'] ? ' #' . (int)$al['entity_id'] : '' ?></span>
                        </div>
                        <span class="text-slate-500 shrink-0 font-mono text-[11px]"><?= e(date('d M Y, g:i A', strtotime($al['created_at']))) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
