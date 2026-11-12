<?php
use App\Core\Session;
$title = __('defaults');
$isPaused = (int)($user['is_paused'] ?? 0) === 1;
$morningTimeLabel = format_time_12($slots['morning']['service_time'] ?? '09:45:00');
$afternoonTimeLabel = format_time_12($slots['afternoon']['service_time'] ?? '15:30:00');
?>
<div class="max-w-4xl mx-auto space-y-4">
    <div class="flex items-center justify-between">
        <a href="/today" class="text-sm font-semibold text-tea-700 inline-flex items-center gap-1">
            <i class="ti ti-arrow-left"></i>
            <span><?= e(__('back')) ?></span>
        </a>
        <h2 class="text-lg md:text-xl font-bold text-slate-800"><?= e(__('defaults')) ?></h2>
        <span></span>
    </div>

    <div class="rounded-2xl bg-tea-50 border border-tea-200 p-3.5 text-xs md:text-sm text-tea-800 flex items-start gap-2">
        <i class="ti ti-info-circle text-base shrink-0 mt-0.5"></i>
        <span>Both Morning (<?= e($morningTimeLabel) ?>) and Afternoon (<?= e($afternoonTimeLabel) ?>) Tea are enabled automatically by default. Uncheck a session to opt out permanently, or skip individual sessions from the Today tab.</span>
    </div>

    <!-- Pause Regular Orders Toggle (Rule 14) -->
    <form method="post" action="/defaults" class="card p-4 flex items-center justify-between gap-3" data-once-submit="1">
        <?= Session::csrfField() ?>
        <input type="hidden" name="form_mode" value="pause">
        <input type="hidden" name="is_paused" value="<?= $isPaused ? '0' : '1' ?>">
        <div>
            <div class="font-bold text-sm text-slate-800 flex items-center gap-1.5">
                <?php if ($isPaused): ?>
                    <i class="ti ti-player-pause text-amberwarm-600 text-base"></i>
                    <span><?= e(__('paused_badge')) ?></span>
                <?php else: ?>
                    <i class="ti ti-circle-check text-emerald-600 text-base"></i>
                    <span>Regular Orders Active</span>
                <?php endif; ?>
            </div>
            <div class="text-xs text-slate-500 mt-0.5"><?= e(__('pause_help')) ?></div>
        </div>
        <button type="submit" class="btn-touch text-xs shrink-0 inline-flex items-center gap-1 <?= $isPaused ? 'bg-tea-600 text-white' : 'bg-amberwarm-100 text-amberwarm-600' ?>">
            <i class="ti <?= $isPaused ? 'ti-player-play' : 'ti-player-pause' ?> text-sm"></i>
            <span><?= $isPaused ? 'Resume' : 'Pause' ?></span>
        </button>
    </form>

    <!-- Morning & Afternoon Recurring Defaults -->
    <form method="post" action="/defaults" class="space-y-4" data-once-submit="1" data-require-online="1">
        <?= Session::csrfField() ?>
        <input type="hidden" name="form_mode" value="defaults">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach (['morning' => ['title' => __('morning'), 'icon' => 'ti-sun'], 'afternoon' => ['title' => __('afternoon'), 'icon' => 'ti-clock']] as $key => $meta):
                $p = $prefs[$key] ?? null;
                $slot = $slots[$key] ?? null;
                $enabled = $p ? ((int)$p['enabled'] === 1) : true;
                $bev = $p['beverage'] ?? 'tea';
                $sug = $p['sugar'] ?? 'normal';
                $qty = (int)($p['quantity'] ?? 1);
                $note = $p['note'] ?? '';
            ?>
            <div class="card p-4 md:p-5 space-y-4">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <h3 class="font-bold text-base text-slate-800 flex items-center gap-1.5">
                            <i class="ti <?= $meta['icon'] ?> text-tea-700 text-lg"></i>
                            <span><?= e($meta['title']) ?></span>
                        </h3>
                        <div class="text-xs text-slate-500"><?= e(format_time_12($slot['service_time'] ?? '')) ?> (Cutoff <?= e(format_time_12($slot['cutoff_time'] ?? '')) ?>)</div>
                    </div>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="<?= $key ?>_enabled" value="1" <?= $enabled ? 'checked' : '' ?> class="w-5 h-5 accent-tea-600 rounded">
                        <span class="text-xs font-bold text-slate-700">Enabled</span>
                    </label>
                </div>

                <div class="grid grid-cols-2 gap-2" data-seg-group>
                    <label class="btn-touch border cursor-pointer text-sm <?= $bev === 'tea' ? 'bg-tea-600 text-white border-tea-600' : 'bg-white text-slate-700 border-stone-200' ?>">
                        <input type="radio" name="<?= $key ?>_beverage" value="tea" class="sr-only" <?= $bev === 'tea' ? 'checked' : '' ?>>
                        <i class="ti ti-cup text-base"></i> <?= e(__('tea')) ?>
                    </label>
                    <label class="btn-touch border cursor-pointer text-sm <?= $bev === 'coffee' ? 'bg-tea-600 text-white border-tea-600' : 'bg-white text-slate-700 border-stone-200' ?>">
                        <input type="radio" name="<?= $key ?>_beverage" value="coffee" class="sr-only" <?= $bev === 'coffee' ? 'checked' : '' ?>>
                        <i class="ti ti-coffee text-base"></i> <?= e(__('coffee')) ?>
                    </label>
                </div>

                <div class="grid grid-cols-3 gap-2" data-seg-group>
                    <?php foreach (['normal' => __('sugar_normal'), 'less' => __('sugar_less'), 'none' => __('sugar_none')] as $sk => $sl): ?>
                        <label class="btn-touch border cursor-pointer text-xs px-2 <?= $sug === $sk ? 'bg-tea-600 text-white border-tea-600' : 'bg-white text-slate-700 border-stone-200' ?>">
                            <input type="radio" name="<?= $key ?>_sugar" value="<?= $sk ?>" class="sr-only" <?= $sug === $sk ? 'checked' : '' ?>>
                            <?= e($sl) ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <span class="text-xs font-semibold text-slate-600"><?= e(__('quantity')) ?></span>
                    <div class="flex items-center gap-2">
                        <button type="button" data-step-delta="-1" class="w-11 h-11 rounded-xl border border-stone-300 bg-white font-bold text-lg inline-flex items-center justify-center"><i class="ti ti-minus"></i></button>
                        <input type="number" name="<?= $key ?>_quantity" min="1" max="5" value="<?= $qty ?>" class="w-14 h-11 text-center font-bold rounded-xl border border-stone-300 bg-white">
                        <button type="button" data-step-delta="1" class="w-11 h-11 rounded-xl border border-stone-300 bg-white font-bold text-lg inline-flex items-center justify-center"><i class="ti ti-plus"></i></button>
                    </div>
                </div>

                <div>
                    <label class="label-text text-xs" for="<?= $key ?>_note"><?= e(__('note')) ?></label>
                    <input id="<?= $key ?>_note" type="text" name="<?= $key ?>_note" value="<?= e($note) ?>" maxlength="180" class="input-field text-sm" placeholder="<?= e(__('note_placeholder')) ?>">
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <button type="submit" class="btn-primary w-full md:w-auto md:px-8 text-base"><?= e(__('save_as_default')) ?></button>
    </form>
</div>
