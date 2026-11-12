<?php
use App\Core\Config;
use App\Core\Session;
$title = __('login');
$showDemo = Config::get('APP_ENV', 'production') === 'local'
    && Config::get('SHOW_DEMO_LOGINS', 'false') === 'true';
$demoUserPass = Config::get('DEFAULT_DEMO_PASSWORD', '');
$demoAdminPass = Config::get('DEFAULT_ADMIN_PASSWORD', '');
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

    <?php if ($showDemo && $demoUserPass !== '' && $demoAdminPass !== ''): ?>
        <div class="pt-3 border-t border-stone-100 space-y-2.5">
            <div class="flex items-center justify-between text-[11px] font-bold uppercase tracking-wide text-slate-500">
                <span class="inline-flex items-center gap-1"><i class="ti ti-flask text-tea-600 text-sm"></i>Local Dev Quick Access</span>
                <span>1-Click Sign In</span>
            </div>
            <div class="grid grid-cols-3 gap-2">
                <button type="button" onclick="quickLogin('EMP001', <?= e(json_encode($demoUserPass)) ?>)" class="rounded-xl border border-tea-200 bg-tea-50/70 hover:bg-tea-100 px-2.5 py-2 text-left transition active:scale-[0.98]">
                    <div class="flex items-center gap-1 text-xs font-bold text-tea-800">
                        <i class="ti ti-user text-sm shrink-0"></i>
                        <span>Employee</span>
                    </div>
                    <div class="text-[10px] font-mono text-tea-700 mt-0.5 truncate">EMP001</div>
                </button>
                <button type="button" onclick="quickLogin('STAFF001', <?= e(json_encode($demoUserPass)) ?>)" class="rounded-xl border border-amberwarm-400/50 bg-amberwarm-50 hover:bg-amberwarm-100 px-2.5 py-2 text-left transition active:scale-[0.98]">
                    <div class="flex items-center gap-1 text-xs font-bold text-amberwarm-700">
                        <i class="ti ti-coffee text-sm shrink-0"></i>
                        <span>Staff</span>
                    </div>
                    <div class="text-[10px] font-mono text-amberwarm-600 mt-0.5 truncate">STAFF001</div>
                </button>
                <button type="button" onclick="quickLogin('ADMIN001', <?= e(json_encode($demoAdminPass)) ?>)" class="rounded-xl border border-indigo-200 bg-indigo-50/80 hover:bg-indigo-100 px-2.5 py-2 text-left transition active:scale-[0.98]">
                    <div class="flex items-center gap-1 text-xs font-bold text-indigo-800">
                        <i class="ti ti-shield-lock text-sm shrink-0"></i>
                        <span>Admin</span>
                    </div>
                    <div class="text-[10px] font-mono text-indigo-700 mt-0.5 truncate">ADMIN001</div>
                </button>
            </div>
        </div>
        <script>
        function quickLogin(id, pass) {
            var f = document.getElementById('login-form');
            var u = document.getElementById('employee_id');
            var p = document.getElementById('password');
            if (!f || !u || !p) return;
            u.value = id;
            p.value = pass;
            f.submit();
        }
        </script>
    <?php endif; ?>

    <?php if ($allowReg): ?>
        <div class="pt-3 border-t border-stone-100 text-center">
            <a href="/register" class="text-sm font-semibold text-tea-700 hover:underline"><?= e(__('register')) ?> &rarr;</a>
        </div>
    <?php endif; ?>
</div>
