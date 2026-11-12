<?php
$title = __('reports');
?>
<form method="get" action="/admin/reports" class="card p-4 flex flex-wrap gap-3 items-end">
    <input type="hidden" name="daily_per_page" value="<?= (int)($dailyPg['per_page'] ?? 25) ?>">
    <input type="hidden" name="dept_per_page" value="<?= (int)($deptPg['per_page'] ?? 25) ?>">
    <div>
        <label class="label-text text-xs">From Date</label>
        <input type="date" name="from" value="<?= e($from) ?>" class="input-field text-xs">
    </div>
    <div>
        <label class="label-text text-xs">To Date</label>
        <input type="date" name="to" value="<?= e($to) ?>" class="input-field text-xs">
    </div>
    <button type="submit" class="btn-primary text-xs">Generate Report</button>
    <a href="/admin/reports?from=<?= e($from) ?>&to=<?= e($to) ?>&export=csv" class="btn-amber text-xs inline-flex items-center gap-1.5">
        <i class="ti ti-download text-sm"></i>Export CSV
    </a>
</form>

<section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="card p-4">
        <div class="text-xs text-slate-500">Delivered Cups</div>
        <div class="text-2xl font-extrabold text-emerald-700"><?= (int)($totals['delivered'] ?? 0) ?></div>
    </div>
    <div class="card p-4">
        <div class="text-xs text-slate-500">Tea Delivered</div>
        <div class="text-2xl font-extrabold text-tea-800 inline-flex items-center gap-1.5"><i class="ti ti-cup text-tea-700"></i><?= (int)($totals['tea'] ?? 0) ?></div>
    </div>
    <div class="card p-4">
        <div class="text-xs text-slate-500">Coffee Delivered</div>
        <div class="text-2xl font-extrabold text-amberwarm-600 inline-flex items-center gap-1.5"><i class="ti ti-coffee text-amberwarm-600"></i><?= (int)($totals['coffee'] ?? 0) ?></div>
    </div>
    <div class="card p-4">
        <div class="text-xs text-slate-500">Cancelled Cups</div>
        <div class="text-2xl font-extrabold text-rose-600"><?= (int)($totals['cancelled'] ?? 0) ?></div>
    </div>
</section>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="card p-5 space-y-3 overflow-x-auto">
        <h2 class="font-bold text-base text-slate-800">Daily Breakdown</h2>
        <table class="w-full text-sm">
            <thead class="text-xs text-slate-500 border-b border-stone-200">
                <tr>
                    <th class="pb-2 text-left">Date</th>
                    <th class="pb-2 text-right">Req</th>
                    <th class="pb-2 text-right">Tea</th>
                    <th class="pb-2 text-right">Coffee</th>
                    <th class="pb-2 text-right">Cancelled</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                <?php foreach ($dailyRows as $r): ?>
                    <tr>
                        <td class="py-2 font-medium"><?= e($r['order_date']) ?></td>
                        <td class="py-2 text-right"><?= (int)$r['requested_cups'] ?></td>
                        <td class="py-2 text-right text-tea-700 font-semibold"><?= (int)$r['tea_delivered'] ?></td>
                        <td class="py-2 text-right text-amberwarm-600 font-semibold"><?= (int)$r['coffee_delivered'] ?></td>
                        <td class="py-2 text-right text-rose-600"><?= (int)$r['cancelled_cups'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        $pgMeta = $dailyPg ?? null;
        $pgBaseUrl = '/admin/reports';
        $pgQuery = [
            'from'          => $from,
            'to'            => $to,
            'dept_page'     => (int)($deptPg['page'] ?? 1),
            'dept_per_page' => (int)($deptPg['per_page'] ?? 25),
        ];
        require __DIR__ . '/../partials/pagination.php';
        ?>
    </div>

    <div class="card p-5 space-y-3 overflow-x-auto">
        <h2 class="font-bold text-base text-slate-800">Department Summary</h2>
        <table class="w-full text-sm">
            <thead class="text-xs text-slate-500 border-b border-stone-200">
                <tr>
                    <th class="pb-2 text-left">Department</th>
                    <th class="pb-2 text-right">Tea</th>
                    <th class="pb-2 text-right">Coffee</th>
                    <th class="pb-2 text-right">Delivered</th>
                    <th class="pb-2 text-right">Cancelled</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                <?php foreach ($deptRows as $r): ?>
                    <tr>
                        <td class="py-2 font-medium"><?= e($r['department_name']) ?></td>
                        <td class="py-2 text-right"><?= (int)$r['tea_cups'] ?></td>
                        <td class="py-2 text-right"><?= (int)$r['coffee_cups'] ?></td>
                        <td class="py-2 text-right font-bold text-emerald-700"><?= (int)$r['delivered_cups'] ?></td>
                        <td class="py-2 text-right text-rose-600"><?= (int)$r['cancelled_cups'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        $pgMeta = $deptPg ?? null;
        $pgBaseUrl = '/admin/reports';
        $pgQuery = [
            'from'           => $from,
            'to'             => $to,
            'daily_page'     => (int)($dailyPg['page'] ?? 1),
            'daily_per_page' => (int)($dailyPg['per_page'] ?? 25),
        ];
        require __DIR__ . '/../partials/pagination.php';
        ?>
    </div>
</div>
