<?php
use App\Core\Session;
$title = __('register');
?>
<div class="card p-6 space-y-5">
    <div>
        <h2 class="text-xl font-bold text-slate-800"><?= e(__('register')) ?></h2>
        <p class="text-xs text-slate-500 mt-0.5">Select your department, room, and upload your cup photo</p>
    </div>

    <form method="post" action="/register" enctype="multipart/form-data" class="space-y-4" data-once-submit="1">
        <?= Session::csrfField() ?>
        <div>
            <label class="label-text" for="full_name"><?= e(__('full_name')) ?> *</label>
            <input id="full_name" name="full_name" type="text" required class="input-field" placeholder="e.g. Rahim Uddin">
        </div>
        <div>
            <label class="label-text" for="employee_id"><?= e(__('employee_id')) ?> *</label>
            <input id="employee_id" name="employee_id" type="text" required class="input-field uppercase" placeholder="e.g. EMP105">
        </div>
        <div>
            <label class="label-text" for="department_id"><?= e(__('department')) ?> *</label>
            <select id="department_id" name="department_id" required class="input-field">
                <option value=""><?= e(__('select_department')) ?></option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="label-text" for="room_id"><?= e(__('room')) ?> *</label>
            <select id="room_id" name="room_id" required class="input-field">
                <option value=""><?= e(__('select_room')) ?></option>
                <?php foreach ($rooms as $r): ?>
                    <option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?><?= !empty($r['floor']) ? ' (' . e($r['floor']) . ')' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="label-text" for="cup_photo"><?= e(__('cup_photo')) ?></label>
            <input id="cup_photo" name="cup_photo" type="file" accept="image/jpeg,image/png,image/webp" class="w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-tea-50 file:text-tea-700">
            <p class="text-[11px] text-slate-400 mt-1"><?= e(__('cup_photo_hint')) ?></p>
        </div>
        <div>
            <label class="label-text" for="password"><?= e(__('password')) ?> * (min 6 chars)</label>
            <input id="password" name="password" type="password" required minlength="6" class="input-field">
        </div>
        <button type="submit" class="btn-primary w-full text-base"><?= e(__('register')) ?></button>
    </form>

    <div class="pt-3 border-t border-stone-100 text-center">
        <a href="/login" class="text-sm font-semibold text-tea-700 hover:underline">← <?= e(__('login')) ?></a>
    </div>
</div>
