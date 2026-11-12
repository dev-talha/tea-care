<?php
use App\Core\Session;
$title = __('profile');
?>
<div class="max-w-2xl mx-auto space-y-4">
<h2 class="text-lg font-bold text-slate-800"><?= e(__('profile')) ?></h2>

<div class="card p-5 space-y-4">
    <div class="flex items-center gap-4">
        <img src="<?= e(cup_image_url($user['cup_image'] ?? null, (int)$user['id'])) ?>" alt="Cup Photo" class="w-20 h-20 rounded-2xl object-cover border border-stone-200 bg-tea-50">
        <div>
            <div class="font-bold text-base text-slate-800"><?= e($user['full_name']) ?></div>
            <div class="text-xs font-semibold text-tea-700"><?= e($user['employee_id']) ?> &bull; <?= e(ucfirst($user['role'])) ?></div>
            <div class="text-xs text-slate-500 mt-0.5"><?= e($user['department_name'] ?? '-') ?> &bull; <?= e($user['room_name'] ?? '-') ?></div>
        </div>
    </div>

    <form method="post" action="/profile" enctype="multipart/form-data" class="space-y-4 pt-2 border-t border-stone-100" data-once-submit="1">
        <?= Session::csrfField() ?>

        <div>
            <label class="label-text" for="full_name"><?= e(__('full_name')) ?></label>
            <input id="full_name" name="full_name" type="text" required value="<?= e($user['full_name']) ?>" class="input-field">
        </div>

        <div>
            <label class="label-text" for="department_id"><?= e(__('department')) ?></label>
            <select id="department_id" name="department_id" class="input-field" <?= $canEditDeptRoom ? '' : 'disabled' ?>>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= (int)$user['department_id'] === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="label-text" for="room_id"><?= e(__('room')) ?></label>
            <select id="room_id" name="room_id" class="input-field" <?= $canEditDeptRoom ? '' : 'disabled' ?>>
                <?php foreach ($rooms as $r): ?>
                    <option value="<?= (int)$r['id'] ?>" <?= (int)$user['room_id'] === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!$canEditDeptRoom): ?>
                <p class="text-[11px] text-slate-400 mt-1">Department and room updates are managed by Admin.</p>
            <?php endif; ?>
        </div>

        <div>
            <label class="label-text" for="cup_photo"><?= e(__('cup_photo')) ?></label>
            <input id="cup_photo" name="cup_photo" type="file" accept="image/jpeg,image/png,image/webp" class="w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-tea-50 file:text-tea-700">
            <p class="text-[11px] text-slate-400 mt-1"><?= e(__('cup_photo_hint')) ?></p>
        </div>

        <div>
            <label class="label-text" for="new_password">Change Password (Optional)</label>
            <input id="new_password" name="new_password" type="password" minlength="6" class="input-field" placeholder="Leave blank to keep current password">
        </div>

        <button type="submit" class="btn-primary w-full"><?= e(__('save')) ?></button>
    </form>

    <?php if ($canGuestOrder): ?>
        <div class="pt-3 border-t border-stone-100">
            <a href="/guest-order" class="btn-outline w-full text-sm border-amberwarm-400/50 bg-amberwarm-50 text-amberwarm-600 inline-flex items-center justify-center gap-1.5">
                <i class="ti ti-users text-base"></i>
                <span><?= e(__('guest_order')) ?></span>
            </a>
        </div>
    <?php endif; ?>

    <form method="post" action="/logout" class="pt-3 border-t border-stone-100">
        <?= Session::csrfField() ?>
        <button type="submit" class="btn-outline w-full text-rose-600 border-rose-200 hover:bg-rose-50 inline-flex items-center justify-center gap-1.5">
            <i class="ti ti-logout text-base"></i>
            <span><?= e(__('logout')) ?></span>
        </button>
    </form>
</div>
</div>
