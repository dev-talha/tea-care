<?php
use App\Core\Session;
$title = __('guest_order_title');
?>
<div class="max-w-2xl mx-auto space-y-4">
<div class="flex items-center justify-between gap-2">
    <a href="/today" class="text-sm font-semibold text-tea-700 inline-flex items-center gap-1 shrink-0">
        <i class="ti ti-arrow-left"></i>
        <span><?= e(__('back')) ?></span>
    </a>
    <h2 class="text-lg font-bold text-slate-800 text-right"><?= e(__('guest_order_title')) ?></h2>
</div>

<div class="card p-4 sm:p-5 space-y-4">
    <form method="post" action="/guest-order" class="space-y-4" data-once-submit="1" data-require-online="1" data-offline-msg="<?= e(__('offline_error')) ?>">
        <?= Session::csrfField() ?>
        <input type="hidden" name="idempotency_key" value="<?= e($idempotencyKey) ?>">

        <div>
            <label class="label-text" for="room_id"><?= e(__('meeting_room')) ?> *</label>
            <select id="room_id" name="room_id" required class="input-field">
                <option value="" selected disabled>Select room</option>
                <?php foreach ($rooms as $r): ?>
                    <option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?><?= !empty($r['floor']) ? ' (' . e($r['floor']) . ')' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="label-text" for="guest_count"><?= e(__('guest_count')) ?></label>
                <input id="guest_count" name="guest_count" type="number" min="1" max="100" class="input-field" placeholder="e.g. 5">
            </div>
            <div>
                <label class="label-text" for="needed_mode">When Needed (Approx.)</label>
                <div class="flex items-center gap-2">
                    <select id="needed_mode" name="needed_mode" class="input-field flex-1 min-w-0">
                        <option value="" selected>Select timing (Optional)</option>
                        <option value="Immediate">Immediate</option>
                        <option value="After">After</option>
                        <option value="Before">Before</option>
                        <option value="Within">Within</option>
                    </select>
                    <div id="needed-min-wrap" class="hidden items-center gap-1.5 shrink-0">
                        <input id="needed_minutes" name="needed_minutes" type="number" min="1" max="480" inputmode="numeric" placeholder="Min" class="input-field w-24 text-center font-semibold">
                        <span class="text-xs font-semibold text-slate-600">min</span>
                    </div>
                </div>
                <input type="hidden" id="requested_within" name="requested_within" value="">
            </div>
        </div>

        <div class="flex items-center justify-between gap-2 pt-1">
            <span class="label-text mb-0">Beverage Items</span>
            <button type="button" onclick="addGuestLine()" class="btn-outline min-h-[38px] text-xs py-1.5 px-3 text-tea-700 border-tea-200 bg-tea-50 inline-flex items-center justify-center gap-1 whitespace-nowrap">
                <i class="ti ti-plus text-sm shrink-0"></i>
                <span>Add</span>
            </button>
        </div>

        <!-- Dynamic Beverage Lines -->
        <div id="guest-lines" class="space-y-2.5">
            <div class="guest-line rounded-xl border border-stone-200 bg-cream-50 p-3 flex flex-wrap items-center gap-2">
                <select name="item_beverage[]" class="input-field w-28 text-sm">
                    <option value="tea"><?= e(__('tea')) ?></option>
                    <option value="coffee"><?= e(__('coffee')) ?></option>
                </select>
                <select name="item_sugar[]" class="input-field flex-1 min-w-[120px] text-sm">
                    <option value="normal"><?= e(__('sugar_normal')) ?></option>
                    <option value="less"><?= e(__('sugar_less')) ?></option>
                    <option value="none"><?= e(__('sugar_none')) ?></option>
                </select>
                <div class="flex items-center gap-1.5">
                    <button type="button" data-step-delta="-1" class="w-10 h-11 rounded-xl border border-stone-300 bg-white font-bold inline-flex items-center justify-center"><i class="ti ti-minus"></i></button>
                    <input type="number" name="item_quantity[]" min="1" max="100" value="2" class="w-14 h-11 text-center font-bold rounded-xl border border-stone-300 bg-white text-sm">
                    <button type="button" data-step-delta="1" class="w-10 h-11 rounded-xl border border-stone-300 bg-white font-bold inline-flex items-center justify-center"><i class="ti ti-plus"></i></button>
                </div>
                <button type="button" onclick="removeGuestLine(this)" class="text-rose-500 font-bold px-2 py-1 text-lg" title="Remove"><i class="ti ti-x"></i></button>
            </div>
        </div>

        <div class="text-xs font-semibold text-tea-700" id="guest-total-cups">Total: 2 cup(s)</div>

        <div>
            <label class="label-text" for="note"><?= e(__('note')) ?></label>
            <input id="note" name="note" type="text" maxlength="200" class="input-field" placeholder="e.g. Serve together with water">
        </div>

        <button type="submit" class="btn-primary w-full text-base inline-flex items-center justify-center gap-1.5">
            <i class="ti ti-send text-lg"></i>
            <span><?= e(__('submit_order')) ?></span>
        </button>
    </form>
</div>
</div>

<template id="guest-line-tpl">
    <div class="guest-line rounded-xl border border-stone-200 bg-cream-50 p-3 flex flex-wrap items-center gap-2">
        <select name="item_beverage[]" class="input-field w-28 text-sm">
            <option value="tea"><?= e(__('tea')) ?></option>
            <option value="coffee"><?= e(__('coffee')) ?></option>
        </select>
        <select name="item_sugar[]" class="input-field flex-1 min-w-[120px] text-sm">
            <option value="normal"><?= e(__('sugar_normal')) ?></option>
            <option value="less"><?= e(__('sugar_less')) ?></option>
            <option value="none"><?= e(__('sugar_none')) ?></option>
        </select>
        <div class="flex items-center gap-1.5">
            <button type="button" data-step-delta="-1" class="w-10 h-11 rounded-xl border border-stone-300 bg-white font-bold inline-flex items-center justify-center"><i class="ti ti-minus"></i></button>
            <input type="number" name="item_quantity[]" min="1" max="100" value="1" class="w-14 h-11 text-center font-bold rounded-xl border border-stone-300 bg-white text-sm">
            <button type="button" data-step-delta="1" class="w-10 h-11 rounded-xl border border-stone-300 bg-white font-bold inline-flex items-center justify-center"><i class="ti ti-plus"></i></button>
        </div>
        <button type="button" onclick="removeGuestLine(this)" class="text-rose-500 font-bold px-2 py-1 text-lg" title="Remove"><i class="ti ti-x"></i></button>
    </div>
</template>

<script>
function updateGuestTotal() {
    let sum = 0;
    document.querySelectorAll('#guest-lines input[name="item_quantity[]"]').forEach((el) => {
        sum += Math.max(0, parseInt(el.value || '0', 10));
    });
    const totalEl = document.getElementById('guest-total-cups');
    if (totalEl) totalEl.textContent = 'Total: ' + sum + ' cup(s)';
}
function addGuestLine(bev = 'tea', sug = 'normal', qty = 1) {
    const tpl = document.getElementById('guest-line-tpl').content.cloneNode(true);
    tpl.querySelector('select[name="item_beverage[]"]').value = bev;
    tpl.querySelector('select[name="item_sugar[]"]').value = sug;
    tpl.querySelector('input[name="item_quantity[]"]').value = qty;
    document.getElementById('guest-lines').appendChild(tpl);
    updateGuestTotal();
}
function removeGuestLine(btn) {
    const lines = document.querySelectorAll('#guest-lines .guest-line');
    if (lines.length <= 1) return;
    btn.closest('.guest-line').remove();
    updateGuestTotal();
}
document.addEventListener('change', (e) => {
    if (e.target && e.target.name === 'item_quantity[]') updateGuestTotal();
});

(() => {
    const modeEl = document.getElementById('needed_mode');
    const minWrap = document.getElementById('needed-min-wrap');
    const minEl = document.getElementById('needed_minutes');
    const hiddenEl = document.getElementById('requested_within');
    if (!modeEl || !minWrap || !minEl || !hiddenEl) return;

    const syncNeededField = (focusMin = false) => {
        const mode = modeEl.value;
        const needsMins = mode === 'After' || mode === 'Before' || mode === 'Within';
        minWrap.classList.toggle('hidden', !needsMins);
        minWrap.classList.toggle('flex', needsMins);
        minEl.required = needsMins;

        if (mode === 'Immediate') {
            hiddenEl.value = 'Immediate';
            minEl.value = '';
        } else if (needsMins) {
            const mins = parseInt(minEl.value || '0', 10);
            hiddenEl.value = mins > 0 ? (mode + ' ' + mins + ' min') : '';
            if (focusMin) minEl.focus();
        } else {
            hiddenEl.value = '';
            minEl.value = '';
        }
    };

    modeEl.addEventListener('change', () => syncNeededField(true));
    minEl.addEventListener('input', () => syncNeededField(false));
    minEl.addEventListener('change', () => syncNeededField(false));
    const form = modeEl.closest('form');
    if (form) form.addEventListener('submit', () => syncNeededField(false));
})();
</script>
