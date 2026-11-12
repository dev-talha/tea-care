<?php
use App\Core\Session;
$title = __('notifications');
$notifBaseQuery = [
    'sub_page'      => (int)($subPg['page'] ?? 1),
    'sub_per_page'  => (int)($subPg['per_page'] ?? 25),
    'job_page'      => (int)($jobPg['page'] ?? 1),
    'job_per_page'  => (int)($jobPg['per_page'] ?? 25),
    'log_page'      => (int)($logPg['page'] ?? 1),
    'log_per_page'  => (int)($logPg['per_page'] ?? 25),
    'wa_page'       => (int)($waPg['page'] ?? 1),
    'wa_per_page'   => (int)($waPg['per_page'] ?? 25),
];
?>
<div class="card p-5 space-y-3">
    <div class="flex items-center justify-between">
        <h2 class="font-bold text-base text-slate-800">Staff Push Subscriptions (<?= (int)($subPg['total_items'] ?? count($subscriptions)) ?>)</h2>
        <form method="post" action="/admin/notifications">
            <?= Session::csrfField() ?>
            <input type="hidden" name="action" value="retry_failed">
            <button type="submit" class="btn-outline text-xs py-2">Retry Failed Jobs</button>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-cream-100 uppercase text-slate-600">
                <tr>
                    <th class="px-3 py-2 text-left">Staff</th>
                    <th class="px-3 py-2 text-left">Device / Browser</th>
                    <th class="px-3 py-2 text-left">Status</th>
                    <th class="px-3 py-2 text-left">Last Success / Error</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                <?php if (empty($subscriptions)): ?>
                    <tr><td colspan="4" class="px-3 py-4 text-center text-slate-500">No push devices registered yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($subscriptions as $s): ?>
                        <tr>
                            <td class="px-3 py-2 font-bold"><?= e($s['full_name']) ?> (<?= e($s['employee_id']) ?>)</td>
                            <td class="px-3 py-2 max-w-xs truncate"><?= e($s['user_agent']) ?></td>
                            <td class="px-3 py-2">
                                <span class="px-2 py-0.5 rounded-full font-bold <?= (int)$s['is_active'] === 1 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' ?>">
                                    <?= (int)$s['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td class="px-3 py-2 text-slate-500"><?= e($s['last_error'] ?: ($s['last_success_at'] ?: 'Pending')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    $pgMeta = $subPg ?? null;
    $pgBaseUrl = '/admin/notifications';
    $pgQuery = $notifBaseQuery;
    require __DIR__ . '/../partials/pagination.php';
    ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="card p-5 space-y-3 overflow-x-auto">
        <h3 class="font-bold text-sm text-slate-800">Push Jobs Queue</h3>
        <table class="w-full text-xs">
            <thead class="text-slate-500 border-b">
                <tr><th class="pb-2 text-left">Title</th><th class="pb-2">Status</th><th class="pb-2">Tries</th><th class="pb-2 text-left">Info</th></tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                <?php if (empty($jobs)): ?>
                    <tr><td colspan="4" class="py-4 text-center text-slate-500">No push jobs queued yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($jobs as $j): ?>
                        <tr>
                            <td class="py-2 font-semibold"><?= e($j['title']) ?></td>
                            <td class="py-2 text-center"><?= e($j['status']) ?></td>
                            <td class="py-2 text-center"><?= (int)$j['attempts'] ?>/<?= (int)$j['max_attempts'] ?></td>
                            <td class="py-2 text-slate-500"><?= e($j['last_error'] ?: $j['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
        $pgMeta = $jobPg ?? null;
        $pgBaseUrl = '/admin/notifications';
        $pgQuery = $notifBaseQuery;
        require __DIR__ . '/../partials/pagination.php';
        ?>
    </div>

    <div class="card p-5 space-y-3 overflow-x-auto">
        <h3 class="font-bold text-sm text-slate-800">Delivery Attempt Logs</h3>
        <table class="w-full text-xs">
            <thead class="text-slate-500 border-b">
                <tr><th class="pb-2 text-left">User</th><th class="pb-2 text-left">Title</th><th class="pb-2">Status</th><th class="pb-2 text-left">Time / Error</th></tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                <?php if (empty($logs)): ?>
                    <tr><td colspan="4" class="py-4 text-center text-slate-500">No delivery logs yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td class="py-2"><?= e($l['full_name'] ?? '-') ?></td>
                            <td class="py-2 font-medium"><?= e($l['title']) ?></td>
                            <td class="py-2 text-center font-bold <?= $l['status'] === 'sent' ? 'text-emerald-700' : 'text-rose-600' ?>"><?= e($l['status']) ?></td>
                            <td class="py-2 text-slate-500"><?= e($l['error_message'] ?: $l['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
        $pgMeta = $logPg ?? null;
        $pgBaseUrl = '/admin/notifications';
        $pgQuery = $notifBaseQuery;
        require __DIR__ . '/../partials/pagination.php';
        ?>
    </div>
</div>

<!-- Evolution API WhatsApp Queue -->
<div class="card p-5 space-y-3 overflow-x-auto">
    <div class="flex items-center justify-between gap-2">
        <h3 class="font-bold text-sm text-slate-800 inline-flex items-center gap-1.5">
            <i class="ti ti-brand-whatsapp text-emerald-600 text-lg"></i>
            <span>WhatsApp (Evolution API) Queue</span>
        </h3>
        <form method="post" action="/admin/notifications">
            <?= Session::csrfField() ?>
            <input type="hidden" name="action" value="retry_failed_wa">
            <button type="submit" class="btn-outline text-xs py-1.5">Retry Failed WhatsApp</button>
        </form>
    </div>
    <table class="w-full text-xs">
        <thead class="text-slate-500 border-b">
            <tr><th class="pb-2 text-left">Order</th><th class="pb-2 text-left">Message Preview</th><th class="pb-2">Status</th><th class="pb-2">Tries</th><th class="pb-2 text-left">Info</th></tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
            <?php if (empty($waJobs)): ?>
                <tr><td colspan="5" class="py-4 text-center text-slate-500">No WhatsApp alerts queued yet.</td></tr>
            <?php else: ?>
                <?php foreach ($waJobs as $wj): ?>
                    <tr>
                        <td class="py-2 font-bold">#<?= (int)($wj['order_id'] ?? 0) ?></td>
                        <td class="py-2 max-w-md truncate"><?= e($wj['message']) ?></td>
                        <td class="py-2 text-center font-bold <?= $wj['status'] === 'sent' ? 'text-emerald-700' : ($wj['status'] === 'failed' ? 'text-rose-600' : 'text-amberwarm-600') ?>"><?= e($wj['status']) ?></td>
                        <td class="py-2 text-center"><?= (int)$wj['attempts'] ?>/<?= (int)$wj['max_attempts'] ?></td>
                        <td class="py-2 text-slate-500"><?= e($wj['last_error'] ?: $wj['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
    $pgMeta = $waPg ?? null;
    $pgBaseUrl = '/admin/notifications';
    $pgQuery = $notifBaseQuery;
    require __DIR__ . '/../partials/pagination.php';
    ?>
</div>
