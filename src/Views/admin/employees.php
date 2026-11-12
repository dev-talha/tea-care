<?php
use App\Core\Session;
$title = __('employees');
?>
<!-- Add Employee Form -->
<div class="card p-5 space-y-4">
    <h2 class="font-bold text-base text-slate-800">Add New Account</h2>
    <form method="post" action="/admin/employees" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3 items-end">
        <?= Session::csrfField() ?>
        <input type="hidden" name="action" value="create">
        <div>
            <label class="label-text text-xs">Employee ID *</label>
            <input name="employee_id" required class="input-field text-sm uppercase" placeholder="EMP101">
        </div>
        <div>
            <label class="label-text text-xs">Full Name *</label>
            <input name="full_name" required class="input-field text-sm" placeholder="Full Name">
        </div>
        <div>
            <label class="label-text text-xs">Role</label>
            <select name="role" class="input-field text-sm">
                <option value="employee">Employee</option>
                <option value="staff">Tea Staff</option>
                <option value="admin">Admin</option>
            </select>
        </div>
        <div>
            <label class="label-text text-xs">Department</label>
            <select name="department_id" class="input-field text-sm">
                <option value="">None</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="label-text text-xs">Room</label>
            <select name="room_id" class="input-field text-sm">
                <option value="">None</option>
                <?php foreach ($rooms as $r): ?>
                    <option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="label-text text-xs">Password * (6+)</label>
            <input name="password" type="password" required minlength="6" class="input-field text-sm">
        </div>
        <div class="md:col-span-3 lg:col-span-6 flex flex-wrap items-center justify-between gap-3">
            <label class="inline-flex items-center gap-2 text-sm font-medium">
                <input type="checkbox" name="can_order_for_guests" value="1" checked class="w-4 h-4 accent-tea-600">
                <span>Allow Guest &amp; Meeting Orders (<code>can_order_for_guests</code>)</span>
            </label>
            <button type="submit" class="btn-primary text-sm">Create Account</button>
        </div>
    </form>
</div>

<!-- Filter & Search -->
<form method="get" action="/admin/employees" class="card p-4 flex flex-wrap gap-3 items-center">
    <input type="hidden" name="per_page" value="<?= (int)($usersPg['per_page'] ?? 25) ?>">
    <input name="q" value="<?= e($q) ?>" placeholder="Search name or ID..." class="input-field text-sm max-w-xs">
    <select name="role" class="input-field text-sm w-36">
        <option value="all" <?= $roleFilter === 'all' ? 'selected' : '' ?>>All Roles</option>
        <option value="employee" <?= $roleFilter === 'employee' ? 'selected' : '' ?>>Employee</option>
        <option value="staff" <?= $roleFilter === 'staff' ? 'selected' : '' ?>>Tea Staff</option>
        <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
    </select>
    <select name="status" class="input-field text-sm w-36">
        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="disabled" <?= $statusFilter === 'disabled' ? 'selected' : '' ?>>Disabled</option>
    </select>
    <button type="submit" class="btn-outline text-sm">Filter</button>
</form>

<!-- Employees Table -->
<div class="card overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-cream-100 text-xs uppercase text-slate-600">
            <tr>
                <th class="px-4 py-3 text-left">Employee</th>
                <th class="px-4 py-3 text-left">Role &amp; Guest</th>
                <th class="px-4 py-3 text-left">Dept &amp; Room</th>
                <th class="px-4 py-3 text-left">Status &amp; Created</th>
                <th class="px-4 py-3 text-right">Manage</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
            <?php foreach ($users as $u):
                $uCup = cup_image_url($u['cup_image'] ?? null, (int)$u['id']);
            ?>
                <tr>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <button type="button" data-cup-preview="1" data-cup-src="<?= e($uCup) ?>" data-cup-name="<?= e($u['full_name']) ?>" data-cup-empid="<?= e($u['employee_id']) ?>" data-cup-dept="<?= e($u['department_name'] ?? '-') ?>" data-cup-room="<?= e($u['room_name'] ?? '-') ?>" title="Enlarge cup photo" class="w-10 h-10 rounded-xl overflow-hidden bg-tea-50 border border-stone-200 hover:border-tea-500 transition shrink-0 cursor-zoom-in">
                                <img src="<?= e($uCup) ?>" alt="Cup" class="w-full h-full object-cover">
                            </button>
                            <div>
                                <div class="font-bold text-slate-800"><?= e($u['full_name']) ?></div>
                                <div class="text-xs text-slate-500"><?= e($u['employee_id']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded-md bg-stone-100 text-xs font-bold uppercase"><?= e($u['role']) ?></span>
                        <?php if ((int)$u['can_order_for_guests'] === 1): ?>
                            <span class="ml-1 px-2 py-0.5 rounded-md bg-amberwarm-100 text-amberwarm-600 text-xs font-semibold">Guest OK</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-600">
                        <div><?= e($u['department_name'] ?? '-') ?></div>
                        <div class="text-slate-400"><?= e($u['room_name'] ?? '-') ?></div>
                    </td>
                    <td class="px-4 py-3">
                        <?php $sc = $u['status'] === 'active' ? 'bg-emerald-100 text-emerald-700' : ($u['status'] === 'pending' ? 'bg-amberwarm-100 text-amberwarm-600' : 'bg-rose-100 text-rose-700'); ?>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold <?= $sc ?>"><?= e(ucfirst($u['status'])) ?></span>
                        <div class="text-[11px] text-slate-400 mt-1"><?= e(date('d M Y, g:i A', strtotime($u['created_at']))) ?></div>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="inline-flex items-center gap-2">
                            <?php if ($u['status'] === 'pending'): ?>
                                <form method="post" action="/admin/employees">
                                    <?= Session::csrfField() ?>
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                    <button type="submit" class="px-2.5 py-1 rounded-lg bg-emerald-600 text-white text-xs font-bold">Approve</button>
                                </form>
                            <?php endif; ?>
                            <button type="button" onclick="document.getElementById('edit-user-<?= (int)$u['id'] ?>').classList.toggle('hidden')" class="px-2.5 py-1 rounded-lg border border-stone-300 text-xs font-semibold">Edit</button>
                        </div>
                    </td>
                </tr>
                <tr id="edit-user-<?= (int)$u['id'] ?>" class="hidden bg-cream-50">
                    <td colspan="5" class="px-4 py-4">
                        <form method="post" action="/admin/employees" class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
                            <?= Session::csrfField() ?>
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <div>
                                <label class="label-text text-xs">Name</label>
                                <input name="full_name" value="<?= e($u['full_name']) ?>" class="input-field text-xs">
                            </div>
                            <div>
                                <label class="label-text text-xs">Role</label>
                                <select name="role" class="input-field text-xs">
                                    <option value="employee" <?= $u['role'] === 'employee' ? 'selected' : '' ?>>Employee</option>
                                    <option value="staff" <?= $u['role'] === 'staff' ? 'selected' : '' ?>>Tea Staff</option>
                                    <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                            </div>
                            <div>
                                <label class="label-text text-xs">Status</label>
                                <select name="status" class="input-field text-xs">
                                    <option value="active" <?= $u['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="pending" <?= $u['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="disabled" <?= $u['status'] === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                                </select>
                            </div>
                            <div>
                                <label class="label-text text-xs">Department</label>
                                <select name="department_id" class="input-field text-xs">
                                    <option value="">None</option>
                                    <?php foreach ($departments as $d): ?>
                                        <option value="<?= (int)$d['id'] ?>" <?= (int)$u['department_id'] === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="label-text text-xs">Room</label>
                                <select name="room_id" class="input-field text-xs">
                                    <option value="">None</option>
                                    <?php foreach ($rooms as $r): ?>
                                        <option value="<?= (int)$r['id'] ?>" <?= (int)$u['room_id'] === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="label-text text-xs">Reset Password</label>
                                <input name="reset_password" type="password" minlength="6" placeholder="Optional" class="input-field text-xs">
                            </div>
                            <div class="md:col-span-6 flex items-center justify-between">
                                <label class="inline-flex items-center gap-2 text-xs font-semibold">
                                    <input type="checkbox" name="can_order_for_guests" value="1" <?= (int)$u['can_order_for_guests'] === 1 ? 'checked' : '' ?> class="w-4 h-4 accent-tea-600">
                                    <span>Can Order for Guests</span>
                                </label>
                                <button type="submit" class="btn-primary text-xs py-2">Save Changes</button>
                            </div>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
$pgMeta = $usersPg ?? null;
$pgBaseUrl = '/admin/employees';
$pgQuery = ['q' => $q, 'role' => $roleFilter, 'status' => $statusFilter];
require __DIR__ . '/../partials/pagination.php';
?>
