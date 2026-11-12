<?php
use App\Core\Session;
$title = __('on_demand_title');
?>
<div class="max-w-2xl mx-auto space-y-4">
<div class="flex items-center justify-between">
    <a href="/today" class="text-sm font-semibold text-tea-700 inline-flex items-center gap-1">
        <i class="ti ti-arrow-left"></i>
        <span><?= e(__('back')) ?></span>
    </a>
    <h2 class="text-lg font-bold text-slate-800"><?= e(__('on_demand_title')) ?></h2>
    <span></span>
</div>

<div class="card p-5 space-y-4">
    <p class="text-xs text-slate-500"><?= e(__('on_demand_subtitle')) ?></p>

    <form method="post" action="/order-now" class="space-y-4" data-once-submit="1" data-require-online="1" data-offline-msg="<?= e(__('offline_error')) ?>">
        <?= Session::csrfField() ?>
        <input type="hidden" name="idempotency_key" value="<?= e($idempotencyKey) ?>">

        <div>
            <span class="label-text">Beverage</span>
            <div class="grid grid-cols-2 gap-2.5" data-seg-group>
                <label class="btn-touch border cursor-pointer text-base bg-tea-600 text-white border-tea-600">
                    <input type="radio" name="beverage" value="tea" class="sr-only" checked>
                    <i class="ti ti-cup text-lg"></i> <?= e(__('tea')) ?>
                </label>
                <label class="btn-touch border cursor-pointer text-base bg-white text-slate-700 border-stone-200">
                    <input type="radio" name="beverage" value="coffee" class="sr-only">
                    <i class="ti ti-coffee text-lg"></i> <?= e(__('coffee')) ?>
                </label>
            </div>
        </div>

        <div>
            <span class="label-text">Sugar Preference</span>
            <div class="grid grid-cols-3 gap-2" data-seg-group>
                <label class="btn-touch border cursor-pointer text-xs px-2 bg-tea-600 text-white border-tea-600">
                    <input type="radio" name="sugar" value="normal" class="sr-only" checked>
                    <?= e(__('sugar_normal')) ?>
                </label>
                <label class="btn-touch border cursor-pointer text-xs px-2 bg-white text-slate-700 border-stone-200">
                    <input type="radio" name="sugar" value="less" class="sr-only">
                    <?= e(__('sugar_less')) ?>
                </label>
                <label class="btn-touch border cursor-pointer text-xs px-2 bg-white text-slate-700 border-stone-200">
                    <input type="radio" name="sugar" value="none" class="sr-only">
                    <?= e(__('sugar_none')) ?>
                </label>
            </div>
        </div>

        <div class="flex items-center justify-between gap-3">
            <span class="label-text mb-0"><?= e(__('quantity')) ?></span>
            <div class="flex items-center gap-2">
                <button type="button" data-step-delta="-1" class="w-11 h-11 rounded-xl border border-stone-300 bg-white font-bold text-lg inline-flex items-center justify-center"><i class="ti ti-minus"></i></button>
                <input type="number" name="quantity" min="1" max="20" value="1" class="w-16 h-11 text-center font-bold rounded-xl border border-stone-300 bg-white">
                <button type="button" data-step-delta="1" class="w-11 h-11 rounded-xl border border-stone-300 bg-white font-bold text-lg inline-flex items-center justify-center"><i class="ti ti-plus"></i></button>
            </div>
        </div>

        <div>
            <label class="label-text" for="room_id"><?= e(__('delivery_room')) ?></label>
            <select id="room_id" name="room_id" class="input-field">
                <?php foreach ($rooms as $r): ?>
                    <option value="<?= (int)$r['id'] ?>" <?= (int)($user['room_id'] ?? 0) === (int)$r['id'] ? 'selected' : '' ?>>
                        <?= e($r['name']) ?><?= !empty($r['floor']) ? ' (' . e($r['floor']) . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="label-text" for="note"><?= e(__('note')) ?></label>
            <input id="note" name="note" type="text" maxlength="200" class="input-field" placeholder="<?= e(__('note_placeholder')) ?>">
        </div>

        <button type="submit" class="btn-amber w-full text-base py-3 inline-flex items-center justify-center gap-1.5">
            <i class="ti ti-send text-lg"></i>
            <span><?= e(__('submit_order')) ?></span>
        </button>
    </form>
</div>
</div>
