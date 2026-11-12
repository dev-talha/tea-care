<?php
use App\Core\Session;
$title = __('schedule_holidays');
$dayNames = ['sun' => 'Sunday', 'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday'];
?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Service Times & Working Days -->
    <form method="post" action="/admin/schedule" class="card p-5 space-y-4">
        <?= Session::csrfField() ?>
        <input type="hidden" name="action" value="update_slots">
        <h2 class="font-bold text-base text-slate-800">Service Sessions &amp; Cutoff Times (Asia/Dhaka)</h2>

        <?php foreach (['morning' => 'Morning Tea', 'afternoon' => 'Afternoon Tea'] as $key => $lbl):
            $s = $slots[$key] ?? [];
        ?>
        <div class="rounded-xl border border-stone-200 bg-cream-50 p-4 space-y-3">
            <div class="flex items-center justify-between">
                <span class="font-bold text-sm"><?= e($lbl) ?></span>
                <label class="inline-flex items-center gap-2 text-xs font-semibold">
                    <input type="checkbox" name="<?= $key ?>_is_active" value="1" <?= (int)($s['is_active'] ?? 1) === 1 ? 'checked' : '' ?> class="w-4 h-4 accent-tea-600">
                    Active
                </label>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label-text text-xs">Service Time</label>
                    <input type="time" name="<?= $key ?>_service_time" value="<?= e(substr((string)($s['service_time'] ?? '09:00'), 0, 5)) ?>" class="input-field text-sm">
                </div>
                <div>
                    <label class="label-text text-xs">Cutoff Time</label>
                    <input type="time" name="<?= $key ?>_cutoff_time" value="<?= e(substr((string)($s['cutoff_time'] ?? '08:45'), 0, 5)) ?>" class="input-field text-sm">
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <div>
            <label class="label-text text-xs">Office Working Days</label>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($dayNames as $dk => $dl): ?>
                    <label class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-stone-200 bg-white text-xs font-semibold cursor-pointer">
                        <input type="checkbox" name="working_days[]" value="<?= $dk ?>" <?= in_array($dk, $workingDays, true) ? 'checked' : '' ?> class="accent-tea-600">
                        <?= e($dl) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <button type="submit" class="btn-primary w-full">Save Schedule</button>
    </form>

    <!-- Public Holidays & Manual Finalizer -->
    <div class="space-y-6">
        <div class="card p-5 space-y-4">
            <h2 class="font-bold text-base text-slate-800">Add Holiday / Office Closure</h2>
            <form method="post" action="/admin/schedule" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                <?= Session::csrfField() ?>
                <input type="hidden" name="action" value="add_holiday">
                <div>
                    <label class="label-text text-xs">Date *</label>
                    <input type="date" name="holiday_date" required class="input-field text-xs">
                </div>
                <div>
                    <label class="label-text text-xs">Reason / Holiday Name *</label>
                    <input type="text" name="title" required class="input-field text-xs" placeholder="e.g. Eid Holiday">
                </div>
                <button type="submit" class="btn-primary text-xs">Add Holiday</button>
            </form>

            <div class="divide-y divide-stone-100 text-sm">
                <?php if (empty($holidays)): ?>
                    <p class="text-xs text-slate-500 pt-2">No holidays configured.</p>
                <?php else: ?>
                    <?php foreach ($holidays as $h): ?>
                        <div class="py-2 flex items-center justify-between gap-2">
                            <div>
                                <span class="font-bold"><?= e($h['holiday_date']) ?></span>
                                <span class="text-slate-600 ml-2"><?= e($h['title']) ?></span>
                                <?php if (!empty($h['created_at'])): ?>
                                    <div class="text-[11px] text-slate-400">Added: <?= e(date('d M Y, g:i A', strtotime($h['created_at']))) ?></div>
                                <?php endif; ?>
                            </div>
                            <form method="post" action="/admin/schedule">
                                <?= Session::csrfField() ?>
                                <input type="hidden" name="action" value="delete_holiday">
                                <input type="hidden" name="holiday_id" value="<?= (int)$h['id'] ?>">
                                <button type="submit" class="text-xs text-rose-600 font-semibold">Remove</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card p-5 space-y-3">
            <h2 class="font-bold text-base text-slate-800">Scheduler Recovery / Manual Finalizer</h2>
            <p class="text-xs text-slate-500">The background scheduler automatically finalizes sessions at cutoff. You can also trigger a safe idempotent catch-up check now.</p>
            <form method="post" action="/admin/schedule">
                <?= Session::csrfField() ?>
                <input type="hidden" name="action" value="run_finalizer">
                <button type="submit" class="btn-amber text-xs">Run Cutoff Check Now</button>
            </form>
        </div>
    </div>
</div>
