<?php
use App\Core\Session;
$title = __('login');
?>
<div class="card p-6 space-y-5">
    <div>
        <h2 class="text-xl font-bold text-slate-800"><?= e(__('login')) ?></h2>
        <p class="text-xs text-slate-500 mt-0.5">Use your Employee ID and password</p>
    </div>

    <form id="login-form" method="post" action="/login" class="space-y-4" data-once-submit="1">
        <?= Session::csrfField() ?>
        <div>
            <label class="label-text" for="employee_id"><?= e(__('employee_id')) ?></label>
            <input id="employee_id" name="employee_id" type="text" required autocomplete="username" placeholder="e.g. EMP001" class="input-field uppercase">
        </div>
        <div>
            <label class="label-text" for="password"><?= e(__('password')) ?></label>
            <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="••••••••" class="input-field">
        </div>
        <button type="submit" class="btn-primary w-full text-base"><?= e(__('login')) ?></button>
    </form>

    <?php if ($allowReg): ?>
        <div class="pt-3 border-t border-stone-100 text-center">
            <a href="/register" class="text-sm font-semibold text-tea-700 hover:underline"><?= e(__('register')) ?> &rarr;</a>
        </div>
    <?php endif; ?>
</div>
