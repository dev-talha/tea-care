<?php
use App\Core\Session;
$title = __('rooms');
?>
<div class="card p-5 space-y-4">
    <h2 class="font-bold text-base text-slate-800">Add Room / Conference Room</h2>
    <form method="post" action="/admin/rooms" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
        <?= Session::csrfField() ?>
        <input type="hidden" name="action" value="create">
        <div>
            <label class="label-text text-xs">Room Name / Number *</label>
            <input name="name" required class="input-field text-sm" placeholder="e.g. Room 205">
        </div>
        <div>
            <label class="label-text text-xs">Floor</label>
            <input name="floor" class="input-field text-sm" placeholder="e.g. 2nd Floor">
        </div>
        <div>
            <label class="label-text text-xs">Type</label>
            <select name="room_type" class="input-field text-sm">
                <option value="office">Office Room</option>
                <option value="meeting">Meeting / Conference Room</option>
            </select>
        </div>
        <div>
            <label class="label-text text-xs">Department (Optional)</label>
            <select name="department_id" class="input-field text-sm">
                <option value="">None</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn-primary text-sm">Add Room</button>
    </form>
</div>

<div class="card overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-cream-100 text-xs uppercase text-slate-600">
            <tr>
                <th class="px-4 py-3 text-left">Room</th>
                <th class="px-4 py-3 text-left">Floor</th>
                <th class="px-4 py-3 text-left">Type</th>
                <th class="px-4 py-3 text-left">Department</th>
                <th class="px-4 py-3 text-left">Created At</th>
                <th class="px-4 py-3 text-center">Active</th>
                <th class="px-4 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
            <?php foreach ($rooms as $r): ?>
                <?php $rfId = 'room-form-' . (int)$r['id']; ?>
                <tr>
                    <td class="px-4 py-2.5">
                        <form id="<?= $rfId ?>" method="post" action="/admin/rooms" class="hidden">
                            <?= Session::csrfField() ?>
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        </form>
                        <input form="<?= $rfId ?>" name="name" value="<?= e($r['name']) ?>" class="input-field text-xs py-1.5">
                    </td>
                    <td class="px-4 py-2.5">
                        <input form="<?= $rfId ?>" name="floor" value="<?= e($r['floor'] ?? '') ?>" class="input-field text-xs py-1.5 w-28">
                    </td>
                    <td class="px-4 py-2.5">
                        <select form="<?= $rfId ?>" name="room_type" class="input-field text-xs py-1.5 w-28">
                            <option value="office" <?= $r['room_type'] === 'office' ? 'selected' : '' ?>>Office</option>
                            <option value="meeting" <?= $r['room_type'] === 'meeting' ? 'selected' : '' ?>>Meeting</option>
                        </select>
                    </td>
                    <td class="px-4 py-2.5">
                        <select form="<?= $rfId ?>" name="department_id" class="input-field text-xs py-1.5">
                            <option value="">None</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= (int)$d['id'] ?>" <?= (int)$r['department_id'] === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td class="px-4 py-2.5 text-xs text-slate-500 whitespace-nowrap"><?= !empty($r['created_at']) ? e(date('d M Y, g:i A', strtotime($r['created_at']))) : '-' ?></td>
                    <td class="px-4 py-2.5 text-center">
                        <input form="<?= $rfId ?>" type="checkbox" name="is_active" value="1" <?= (int)$r['is_active'] === 1 ? 'checked' : '' ?> class="w-4 h-4 accent-tea-600">
                    </td>
                    <td class="px-4 py-2.5 text-right whitespace-nowrap">
                        <button form="<?= $rfId ?>" type="submit" class="px-3 py-1.5 rounded-lg bg-tea-600 text-white text-xs font-bold">Save</button>
                        <?php if ((int)$r['user_count'] === 0): ?>
                            <form method="post" action="/admin/rooms" class="inline-block ml-1" onsubmit="return confirm('Delete this room?');">
                                <?= Session::csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="px-2.5 py-1.5 rounded-lg border border-rose-200 text-rose-600 text-xs font-semibold">Del</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
$pgMeta = $roomPg ?? null;
$pgBaseUrl = '/admin/rooms';
$pgQuery = [];
require __DIR__ . '/../partials/pagination.php';
?>
