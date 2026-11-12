<?php
use App\Core\Session;
$title = __('settings');
$evo = $evo ?? ['enabled' => false, 'base_url' => '', 'api_key' => '', 'instance' => '', 'group_jid' => ''];
?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
    <form method="post" action="/admin/settings" enctype="multipart/form-data" class="card p-5 space-y-5">
        <?= Session::csrfField() ?>
        <div class="space-y-4">
            <h2 class="font-bold text-base text-slate-800 flex items-center gap-2">
                <i class="ti ti-settings text-tea-700 text-lg"></i>
                <span>System Settings</span>
            </h2>

            <div>
                <label class="label-text text-xs">Office Name</label>
                <input name="office_name" value="<?= e(setting('office_name', 'TeaCare Office')) ?>" class="input-field text-sm">
            </div>

            <div>
                <label class="label-text text-xs">Office Logo (Optional)</label>
                <input type="file" name="office_logo" accept="image/png,image/jpeg,image/webp" class="w-full text-xs">
            </div>

            <div>
                <label class="label-text text-xs">Timezone</label>
                <input name="timezone" value="<?= e(setting('timezone', 'Asia/Dhaka')) ?>" class="input-field text-sm">
            </div>

            <div class="space-y-2.5 pt-1">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="allow_self_registration" value="1" <?= setting('allow_self_registration', '1') === '1' ? 'checked' : '' ?> class="w-4 h-4 accent-tea-600">
                    Allow Employee Self-Registration
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="require_admin_approval" value="1" <?= setting('require_admin_approval', '0') === '1' ? 'checked' : '' ?> class="w-4 h-4 accent-tea-600">
                    Require Admin Approval for New Registrations
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="allow_employee_dept_room_edit" value="1" <?= setting('allow_employee_dept_room_edit', '1') === '1' ? 'checked' : '' ?> class="w-4 h-4 accent-tea-600">
                    Allow Employees to Update Own Department &amp; Room
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="guest_ordering_enabled" value="1" <?= setting('guest_ordering_enabled', '1') === '1' ? 'checked' : '' ?> class="w-4 h-4 accent-tea-600">
                    Enable Guest / Meeting Ordering Feature
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="push_enabled" value="1" <?= setting('push_enabled', '1') === '1' ? 'checked' : '' ?> class="w-4 h-4 accent-tea-600">
                    Enable Web Push Notifications
                </label>
            </div>
        </div>

        <!-- Evolution API (WhatsApp Group Notifications) -->
        <div class="pt-4 border-t border-stone-200 space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-base text-slate-800 flex items-center gap-2">
                    <i class="ti ti-brand-whatsapp text-emerald-600 text-xl"></i>
                    <span>Evolution API (WhatsApp Staff Group)</span>
                </h3>
                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold <?= $evo['enabled'] ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-slate-500' ?>">
                    <?= $evo['enabled'] ? 'Enabled' : 'Disabled' ?>
                </span>
            </div>
            <p class="text-xs text-slate-500">Connect your external Evolution API server so Urgent On-Demand &amp; Guest Orders automatically notify the Tea Staff WhatsApp group.</p>

            <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                <input type="checkbox" name="evo_enabled" value="1" <?= $evo['enabled'] ? 'checked' : '' ?> class="w-4 h-4 accent-emerald-600">
                Enable Evolution API WhatsApp Notifications
            </label>

            <div>
                <label class="label-text text-xs">Evolution API Base URL</label>
                <input name="evo_base_url" type="url" value="<?= e($evo['base_url']) ?>" placeholder="https://evo.yourdomain.com" class="input-field text-sm">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="label-text text-xs">Instance Name</label>
                    <input name="evo_instance" value="<?= e($evo['instance']) ?>" placeholder="e.g. teacare" class="input-field text-sm">
                </div>
                <div>
                    <label class="label-text text-xs">Staff Group JID / Number</label>
                    <input name="evo_group_jid" value="<?= e($evo['group_jid']) ?>" placeholder="1203630xxxx@g.us" class="input-field text-sm">
                </div>
            </div>

            <div>
                <label class="label-text text-xs">Global / Instance API Key</label>
                <input name="evo_api_key" type="password" autocomplete="new-password" value="" placeholder="<?= $evo['api_key'] !== '' ? 'Configured (leave blank to keep current key)' : 'Enter Evolution API Key' ?>" class="input-field text-sm">
                <?php if ($evo['api_key'] !== ''): ?>
                    <label class="inline-flex items-center gap-1.5 text-[11px] text-rose-600 mt-1 cursor-pointer">
                        <input type="checkbox" name="clear_evo_key" value="1" class="accent-rose-600">
                        Remove stored API key
                    </label>
                <?php endif; ?>
            </div>
        </div>

        <button type="submit" class="btn-primary w-full inline-flex items-center justify-center gap-1.5">
            <i class="ti ti-device-floppy text-base"></i>
            <span>Save Settings</span>
        </button>
    </form>

    <div class="space-y-6">
        <!-- Evolution API Connection Test -->
        <div class="card p-5 space-y-3">
            <h2 class="font-bold text-base text-slate-800 flex items-center gap-2">
                <i class="ti ti-plug-connected text-emerald-600 text-lg"></i>
                <span>Test Evolution API Connection</span>
            </h2>
            <p class="text-xs text-slate-500">Save your Evolution API settings first, then send a live test message to verify WhatsApp group delivery.</p>
            <form method="post" action="/admin/settings/test-whatsapp" data-once-submit="1">
                <?= Session::csrfField() ?>
                <button type="submit" class="btn-outline text-xs border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 inline-flex items-center gap-1.5">
                    <i class="ti ti-send text-sm"></i>
                    <span>Send Test WhatsApp Message</span>
                </button>
            </form>
        </div>

        <div class="card p-5 space-y-2 text-xs">
            <h2 class="font-bold text-base text-slate-800">System Health &amp; Backup</h2>
            <div><strong>Scheduler Last Heartbeat:</strong> <?= e($schedulerLastRun) ?></div>
            <div><strong>VAPID Public Key:</strong> <code class="break-all text-[11px] bg-stone-100 px-1.5 py-0.5 rounded"><?= e($vapid['public_key'] ?: 'Not generated') ?></code></div>
            <div class="pt-2 border-t border-stone-100 text-slate-600 space-y-1">
                <div class="font-bold">Database Backup Command:</div>
                <pre class="bg-slate-900 text-emerald-300 p-2.5 rounded-xl overflow-x-auto text-[11px]">docker exec mysql-main mysqldump -u"$DB_USERNAME" -p"$DB_PASSWORD" teacare &gt; teacare-backup.sql</pre>
            </div>
        </div>

        <div class="card p-5 space-y-3 overflow-x-auto">
            <h2 class="font-bold text-base text-slate-800">Recent Audit Logs</h2>
            <table class="w-full text-xs">
                <thead class="text-slate-500 border-b">
                    <tr><th class="pb-2 text-left">Time</th><th class="pb-2 text-left">Actor</th><th class="pb-2 text-left">Action</th><th class="pb-2 text-left">Entity</th></tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <?php foreach ($auditLogs as $al): ?>
                        <tr>
                            <td class="py-1.5 text-slate-400"><?= e($al['created_at']) ?></td>
                            <td class="py-1.5 font-semibold"><?= e($al['actor_name']) ?></td>
                            <td class="py-1.5"><?= e($al['action']) ?></td>
                            <td class="py-1.5 text-slate-500"><?= e($al['entity_type']) ?>#<?= (int)$al['entity_id'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            $pgMeta = $auditPg ?? null;
            $pgBaseUrl = '/admin/settings';
            $pgQuery = [];
            require __DIR__ . '/../partials/pagination.php';
            ?>
        </div>
    </div>
</div>
