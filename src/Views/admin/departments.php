<?php
use App\Core\Session;
$title = __('departments');
?>
<div class="card p-5 space-y-3">
    <h2 class="font-bold text-base text-slate-800">Add Department</h2>
    <form method="post" action="/admin/departments" class="flex flex-wrap gap-3">
        <?= Session::csrfField() ?>
        <input type="hidden" name="action" value="create">
        <input name="name" required class="input-field max-w-sm" placeholder="Department name">
        <button type="submit" class="btn-primary">Add Department</button>
    </form>
</div>

<div class="card overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-cream-100 text-xs uppercase text-slate-600">
            <tr>
                <th class="px-4 py-3 text-left">Department Name</th>
                <th class="px-4 py-3 text-center">Employees</th>
                <th class="px-4 py-3 text-left">Created At</th>
                <th class="px-4 py-3 text-center">Active</th>
                <th class="px-4 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
            <?php foreach ($departments as $d): ?>
                <tr>
                    <td class="px-4 py-3">
                        <form method="post" action="/admin/departments" class="flex items-center gap-2">
                            <?= Session::csrfField() ?>
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                            <input name="name" value="<?= e($d['name']) ?>" class="input-field text-sm py-1.5 max-w-xs">
                    </td>
                    <td class="px-4 py-3 text-center font-semibold"><?= (int)$d['user_count'] ?></td>
                    <td class="px-4 py-3 text-xs text-slate-500 whitespace-nowrap"><?= !empty($d['created_at']) ? e(date('d M Y, g:i A', strtotime($d['created_at']))) : '-' ?></td>
                    <td class="px-4 py-3 text-center">
                            <input type="checkbox" name="is_active" value="1" <?= (int)$d['is_active'] === 1 ? 'checked' : '' ?> class="w-4 h-4 accent-tea-600">
                    </td>
                    <td class="px-4 py-3 text-right">
                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-tea-600 text-white text-xs font-bold">Save</button>
                        </form>
                        <?php if ((int)$d['user_count'] === 0): ?>
                            <form method="post" action="/admin/departments" class="inline-block ml-1" onsubmit="return confirm('Delete this department?');">
                                <?= Session::csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                <button type="submit" class="px-2.5 py-1.5 rounded-lg border border-rose-200 text-rose-600 text-xs font-semibold">Delete</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
$pgMeta = $deptPg ?? null;
$pgBaseUrl = '/admin/departments';
$pgQuery = [];
require __DIR__ . '/../partials/pagination.php';
?>
