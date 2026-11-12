<?php
use App\Core\Session;
$title = 'Staff Settings';
$csrf = Session::csrfToken();
$morningLabel = format_time_12($morningSlot['service_time'] ?? '09:45:00');
$afternoonLabel = format_time_12($afternoonSlot['service_time'] ?? '15:30:00');
$announcements = $announcements ?? [];
?>
<div class="flex items-center justify-between gap-2">
    <a href="/staff" class="text-sm font-semibold text-tea-700 inline-flex items-center gap-1.5">
        <i class="ti ti-arrow-left"></i>
        <span>Back to Queue</span>
    </a>
    <h2 class="text-lg font-bold text-slate-800">Staff Settings</h2>
    <span class="w-16 hidden sm:inline-block"></span>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
    <!-- Left: Staff Announcement & Serving Time Adjustment -->
    <section class="lg:col-span-6 card p-4 md:p-5 space-y-4">
        <div class="flex items-start justify-between gap-2">
            <div>
                <h3 class="font-bold text-base text-slate-800 inline-flex items-center gap-2">
                    <i class="ti ti-speakerphone text-tea-700 text-lg"></i>
                    <span>Announcement &amp; Serving Time</span>
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">Notify all employees on their dashboard if tea/coffee cannot be served today or if serving time shifts due to busy hours.</p>
            </div>
            <?php if (!empty($announcements)): ?>
                <span class="px-2.5 py-0.5 rounded-full bg-rose-100 text-rose-700 text-[11px] font-extrabold uppercase shrink-0"><?= count($announcements) ?> Active</span>
            <?php endif; ?>
        </div>

        <?php if (!empty($announcements)): ?>
            <div class="space-y-2.5">
                <?php foreach ($announcements as $ann): ?>
                    <div class="rounded-xl border border-rose-200 bg-rose-50/70 p-3 flex items-start justify-between gap-2.5 text-xs">
                        <div class="min-w-0">
                            <div class="font-bold text-rose-800">
                                <?= $ann['announce_type'] === 'unavailable' ? 'Tea/Coffee Unavailable' : ($ann['announce_type'] === 'time_change' ? 'Time Shifted: ' . format_time_12((string)$ann['adjusted_time']) : 'Notice') ?>
                                (<?= e(ucfirst($ann['scope'])) ?>)
                            </div>
                            <div class="text-rose-700 mt-0.5 break-words"><?= e($ann['reason']) ?></div>
                            <div class="text-[11px] text-rose-500 mt-1">Posted: <?= e(date('d M, g:i A', strtotime($ann['created_at']))) ?></div>
                        </div>
                        <form method="post" action="/staff/announcement/clear" class="shrink-0">
                            <?= Session::csrfField() ?>
                            <input type="hidden" name="id" value="<?= (int)$ann['id'] ?>">
                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-white border border-rose-200 text-rose-700 font-bold hover:bg-rose-100">Clear</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/staff/announcement" class="space-y-3 pt-2 border-t border-stone-100" data-once-submit="1">
            <?= Session::csrfField() ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="label-text text-xs" for="ann-type">Notice Type</label>
                    <select id="ann-type" name="announce_type" onchange="toggleAnnTime(this.value)" class="input-field text-xs">
                        <option value="unavailable">Tea/Coffee Unavailable Today</option>
                        <option value="time_change">Adjust Serving Time (Busy)</option>
                        <option value="info">General Notice</option>
                    </select>
                </div>

                <div>
                    <label class="label-text text-xs" for="ann-scope">Session</label>
                    <select id="ann-scope" name="scope" class="input-field text-xs">
                        <option value="all">All Day</option>
                        <option value="morning">Morning (<?= e($morningLabel) ?>)</option>
                        <option value="afternoon">Afternoon (<?= e($afternoonLabel) ?>)</option>
                    </select>
                </div>
            </div>

            <div id="ann-time-wrap" class="hidden">
                <label class="label-text text-xs" for="ann-time-input">New Serving Time *</label>
                <input id="ann-time-input" type="time" name="adjusted_time" value="10:15" class="input-field text-xs">
            </div>

            <div>
                <label class="label-text text-xs" for="ann-reason-input">Short Reason (shown to employees) *</label>
                <input id="ann-reason-input" type="text" name="reason" required maxlength="290" class="input-field text-xs" placeholder="e.g. Pantry gas issue / Busy with meeting, serving 20m late">
            </div>

            <div class="flex flex-wrap gap-1.5 text-[11px]">
                <span class="text-slate-400 py-1 w-full sm:w-auto">Quick fill:</span>
                <button type="button" onclick="fillAnnPreset('unavailable','all','','Unable to serve tea/coffee today due to pantry maintenance.')" class="px-2.5 py-1.5 rounded-lg bg-stone-100 hover:bg-stone-200 text-slate-700 font-semibold">Pantry maintenance</button>
                <button type="button" onclick="fillAnnPreset('time_change','morning','10:15','Staff busy with office work; morning tea shifted slightly.')" class="px-2.5 py-1.5 rounded-lg bg-stone-100 hover:bg-stone-200 text-slate-700 font-semibold">Morning shifted 10:15 AM</button>
                <button type="button" onclick="fillAnnPreset('time_change','afternoon','16:00','Staff busy; afternoon tea will be served at 4:00 PM.')" class="px-2.5 py-1.5 rounded-lg bg-stone-100 hover:bg-stone-200 text-slate-700 font-semibold">Afternoon shifted 4:00 PM</button>
            </div>

            <button type="submit" class="btn-primary w-full text-sm">
                <i class="ti ti-send text-base"></i>
                <span>Publish to Employees</span>
            </button>
        </form>
    </section>

    <!-- Right: Web Push Notifications & Registered Devices -->
    <section class="lg:col-span-6 space-y-4">
        <div class="card p-4 md:p-5 space-y-4">
            <div>
                <h3 class="font-bold text-base text-slate-800 inline-flex items-center gap-2">
                    <i class="ti ti-bell text-tea-700 text-lg"></i>
                    <span>Web Push Notifications</span>
                </h3>
                <p class="text-xs text-slate-500 mt-1">Enable push alerts on this phone or desktop to receive instant alerts for urgent on-demand requests and guest orders.</p>
            </div>

            <div id="push-status-box" class="rounded-xl bg-cream-50 border border-stone-200 p-3 text-xs font-semibold text-slate-700 break-words">
                Checking browser permission...
            </div>

            <div class="flex flex-col sm:flex-row gap-2.5">
                <button id="enable-push-btn" type="button" class="btn-primary flex-1 text-sm">
                    <i class="ti ti-bell-ringing text-base"></i>
                    <span><?= e(__('enable_notifications')) ?></span>
                </button>
                <button id="test-push-btn" type="button" class="btn-outline flex-1 text-sm">
                    <i class="ti ti-send text-base"></i>
                    <span>Send Test Push</span>
                </button>
            </div>

            <p class="text-[11px] text-slate-400">
                On iPhone/iPad: first add TeaCare to your Home Screen via Safari Share menu, then open from the Home Screen icon and tap Enable Notifications.
            </p>
        </div>

        <div class="card p-4 md:p-5 space-y-2.5">
            <h3 class="font-bold text-sm text-slate-800">Registered Devices (<?= count($subs) ?>)</h3>
            <?php if (empty($subs)): ?>
                <p class="text-xs text-slate-500">No devices registered yet for your account.</p>
            <?php else: ?>
                <div class="divide-y divide-stone-100 text-xs">
                    <?php foreach ($subs as $s): ?>
                        <div class="py-2.5 flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <div class="font-semibold truncate text-slate-700"><?= e($s['user_agent'] ?: 'Browser Device') ?></div>
                                <div class="text-[11px] text-slate-400">
                                    Added: <?= e(date('d M Y, g:i A', strtotime($s['created_at']))) ?>
                                </div>
                            </div>
                            <span class="px-2 py-0.5 rounded-full font-bold shrink-0 <?= (int)$s['is_active'] === 1 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' ?>">
                                <?= (int)$s['is_active'] === 1 ? 'Active' : 'Expired' ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<script>
function toggleAnnTime(val) {
    const wrap = document.getElementById('ann-time-wrap');
    if (!wrap) return;
    if (val === 'time_change') {
        wrap.classList.remove('hidden');
    } else {
        wrap.classList.add('hidden');
    }
}
function fillAnnPreset(type, scope, timeVal, reasonTxt) {
    const typeEl = document.getElementById('ann-type');
    const scopeEl = document.getElementById('ann-scope');
    const timeEl = document.getElementById('ann-time-input');
    const reasonEl = document.getElementById('ann-reason-input');
    if (typeEl) { typeEl.value = type; toggleAnnTime(type); }
    if (scopeEl) scopeEl.value = scope;
    if (timeEl && timeVal) timeEl.value = timeVal;
    if (reasonEl) reasonEl.value = reasonTxt;
}
document.addEventListener('DOMContentLoaded', () => {
    const statusBox = document.getElementById('push-status-box');
    const enableBtn = document.getElementById('enable-push-btn');
    const testBtn = document.getElementById('test-push-btn');
    const csrf = <?= json_encode($csrf) ?>;

    function refreshStatus() {
        if (!('Notification' in window)) {
            statusBox.textContent = 'Browser does not support Web Push notifications.';
            return;
        }
        statusBox.textContent = 'Browser Permission: ' + Notification.permission.toUpperCase();
    }
    refreshStatus();

    enableBtn?.addEventListener('click', async () => {
        enableBtn.disabled = true;
        try {
            const res = await window.TeaCarePush.enable(csrf);
            statusBox.textContent = 'Subscribed: ' + (res.message || 'Active!');
            setTimeout(() => location.reload(), 900);
        } catch (err) {
            statusBox.textContent = 'Error: ' + err.message;
        } finally {
            enableBtn.disabled = false;
        }
    });

    testBtn?.addEventListener('click', async () => {
        testBtn.disabled = true;
        try {
            const res = await window.TeaCarePush.test(csrf);
            statusBox.textContent = res.ok ? 'Test notification sent!' : 'Error: ' + (res.error || 'Failed');
        } catch (err) {
            statusBox.textContent = 'Error: ' + err.message;
        } finally {
            testBtn.disabled = false;
        }
    });
});
</script>
