<?php
use App\Core\Session;
$title = __('orders');
?>
<form method="get" action="/admin/orders" class="card p-4 grid grid-cols-2 md:grid-cols-6 gap-3 items-end">
    <input type="hidden" name="per_page" value="<?= (int)($ordersPg['per_page'] ?? 25) ?>">
    <div>
        <label class="label-text text-xs">Date</label>
        <input type="date" name="date" value="<?= e($date) ?>" class="input-field text-xs">
    </div>
    <div>
        <label class="label-text text-xs">Type</label>
        <select name="type" class="input-field text-xs">
            <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>All</option>
            <option value="regular" <?= $type === 'regular' ? 'selected' : '' ?>>Regular</option>
            <option value="on_demand" <?= $type === 'on_demand' ? 'selected' : '' ?>>On-Demand</option>
            <option value="guest" <?= $type === 'guest' ? 'selected' : '' ?>>Guest</option>
        </select>
    </div>
    <div>
        <label class="label-text text-xs">Status</label>
        <select name="status" class="input-field text-xs">
            <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
            <?php foreach (['pending','accepted','preparing','almost_done','delivered','cancelled','system_cancelled'] as $st): ?>
                <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $st))) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="label-text text-xs">Room</label>
        <select name="room" class="input-field text-xs">
            <option value="">All Rooms</option>
            <?php foreach ($rooms as $r): ?>
                <option value="<?= e($r['name']) ?>" <?= $room === $r['name'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="label-text text-xs">Department</label>
        <select name="dept" class="input-field text-xs">
            <option value="">All Depts</option>
            <?php foreach ($depts as $d): ?>
                <option value="<?= e($d['name']) ?>" <?= $dept === $d['name'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn-primary text-xs">Filter</button>
</form>

<div class="card overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-cream-100 text-xs uppercase text-slate-600">
            <tr>
                <th class="px-4 py-3 text-left">ID / Time</th>
                <th class="px-4 py-3 text-left">Employee</th>
                <th class="px-4 py-3 text-left">Type</th>
                <th class="px-4 py-3 text-left">Items</th>
                <th class="px-4 py-3 text-left">Status</th>
                <th class="px-4 py-3 text-right">Correction / Events</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
            <?php if (empty($orders)): ?>
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No orders match the filters.</td></tr>
            <?php else: ?>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td class="px-4 py-3 text-xs">
                        <div class="font-bold">#<?= (int)$o['id'] ?></div>
                        <div class="text-slate-400"><?= e(date('g:i A', strtotime($o['created_at']))) ?></div>
                    </td>
                    <td class="px-4 py-3 text-xs">
                        <div class="font-bold text-slate-800"><?= e($o['employee_name_snapshot']) ?> (<?= e($o['employee_id_snapshot']) ?>)</div>
                        <div class="text-slate-500"><?= e($o['department_name_snapshot']) ?> • <?= e($o['room_name_snapshot']) ?></div>
                    </td>
                    <td class="px-4 py-3 text-xs font-semibold uppercase">
                        <?= e($o['order_type']) ?><?= $o['slot_key'] ? ' (' . e($o['slot_key']) . ')' : '' ?>
                    </td>
                    <td class="px-4 py-3 text-xs">
                        <?php foreach ($o['items'] as $it): ?>
                            <div><?= (int)$it['quantity'] ?>&times; <?= e(badge_beverage($it['beverage'])) ?> (<?= e(badge_sugar($it['sugar'])) ?>)</div>
                        <?php endforeach; ?>
                        <?php if (!empty($o['note'])): ?>
                            <div class="text-slate-400">"<?= e($o['note']) ?>"</div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <?php $status = $o['status']; require __DIR__ . '/../partials/status-badge.php'; ?>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button type="button" onclick="document.getElementById('ord-ev-<?= (int)$o['id'] ?>').classList.toggle('hidden')" class="px-2.5 py-1 rounded-lg border border-stone-300 text-xs font-semibold">Manage</button>
                    </td>
                </tr>
                <tr id="ord-ev-<?= (int)$o['id'] ?>" class="hidden bg-cream-50">
                    <td colspan="6" class="px-4 py-4 space-y-3">
                        <div class="text-xs space-y-1">
                            <div class="font-bold text-slate-700">Order Events:</div>
                            <?php foreach ($o['events'] ?? [] as $ev): ?>
                                <div class="text-slate-600">• <?= e($ev['created_at']) ?> — <strong><?= e($ev['actor_name']) ?></strong>: <?= e($ev['from_status'] ?: 'new') ?> → <strong><?= e($ev['to_status']) ?></strong> <?= $ev['note'] ? '(' . e($ev['note']) . ')' : '' ?></div>
                            <?php endforeach; ?>
                        </div>
                        <form method="post" action="/admin/orders" class="flex flex-wrap gap-2 items-center pt-2 border-t border-stone-200">
                            <?= Session::csrfField() ?>
                            <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                            <select name="status" class="input-field text-xs w-40">
                                <?php foreach (['pending','accepted','preparing','almost_done','delivered','cancelled','system_cancelled'] as $st): ?>
                                    <option value="<?= $st ?>" <?= $o['status'] === $st ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $st))) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input name="reason" required placeholder="Audit reason for correction..." class="input-field text-xs flex-1 min-w-[200px]">
                            <button type="submit" class="btn-amber text-xs py-2">Apply Correction</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$pgMeta = $ordersPg ?? null;
$pgBaseUrl = '/admin/orders';
$pgQuery = ['date' => $date, 'type' => $type, 'status' => $status, 'room' => $room, 'dept' => $dept];
require __DIR__ . '/../partials/pagination.php';
?>
