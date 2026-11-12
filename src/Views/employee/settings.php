<?php
use App\Core\Session;
$title = 'Settings';
$isPaused = (int)($user['is_paused'] ?? 0) === 1;
$morningTimeLabel = format_time_12($slots['morning']['service_time'] ?? '09:45:00');
$afternoonTimeLabel = format_time_12($slots['afternoon']['service_time'] ?? '15:30:00');
$curTab = $settingsTab ?? 'profile';
$monkeyOn = (int)($user['show_funny_monkey'] ?? 1) === 1;
$monkeyDismissedToday = (($user['monkey_dismissed_date'] ?? '') === \App\Services\ScheduleService::today());
?>
<div class="max-w-3xl mx-auto space-y-4">
    <div class="flex items-center justify-between gap-2">
        <a href="/today" class="text-sm font-semibold text-tea-700 inline-flex items-center gap-1 shrink-0">
            <i class="ti ti-arrow-left"></i>
            <span><?= e(__('back')) ?></span>
        </a>
        <h2 class="text-lg md:text-xl font-bold text-slate-800 inline-flex items-center gap-2 text-right">
            <i class="ti ti-settings text-tea-700"></i>
            <span>Settings</span>
        </h2>
    </div>

    <!-- Clean Segmented Settings Tabs (Desktop & Mobile) -->
    <div class="bg-white border border-stone-200 rounded-2xl p-1 shadow-sm grid grid-cols-3 gap-1" role="tablist">
        <button type="button"
                role="tab"
                data-settings-tab="profile"
                onclick="switchSettingsTab('profile')"
                class="min-h-[40px] px-2 py-2 rounded-xl text-xs sm:text-sm font-bold flex items-center justify-center whitespace-nowrap transition <?= $curTab === 'profile' ? 'bg-tea-600 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-100' ?>">
            <span>Profile</span>
        </button>
        <button type="button"
                role="tab"
                data-settings-tab="regular"
                onclick="switchSettingsTab('regular')"
                class="min-h-[40px] px-2 py-2 rounded-xl text-xs sm:text-sm font-bold flex items-center justify-center whitespace-nowrap transition <?= $curTab === 'regular' ? 'bg-tea-600 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-100' ?>">
            <span>Tea Defaults</span>
        </button>
        <button type="button"
                role="tab"
                data-settings-tab="fun"
                onclick="switchSettingsTab('fun')"
                class="min-h-[40px] px-2 py-2 rounded-xl text-xs sm:text-sm font-bold flex items-center justify-center whitespace-nowrap transition <?= $curTab === 'fun' ? 'bg-tea-600 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-100' ?>">
            <span>Companion</span>
        </button>
    </div>

    <!-- TAB 1: Regular Tea & Schedule Preferences -->
    <section id="settings-panel-regular" data-settings-panel="regular" class="space-y-4 <?= $curTab === 'regular' ? '' : 'hidden' ?>">
        <div class="card p-4 flex items-center justify-between gap-3">
            <div>
                <div class="font-bold text-sm text-slate-800 flex items-center gap-1.5">
                    <?php if ($isPaused): ?>
                        <i class="ti ti-power text-rose-600 text-base"></i>
                        <span>Regular Orders: OFF</span>
                    <?php else: ?>
                        <i class="ti ti-circle-check text-emerald-600 text-base"></i>
                        <span>Regular Orders: ON</span>
                    <?php endif; ?>
                </div>
                <div class="text-xs text-slate-500 mt-0.5"><?= e(__('pause_help')) ?></div>
            </div>
            <div class="inline-flex rounded-xl border border-stone-200 bg-stone-100 p-1 shrink-0 gap-1">
                <form method="post" action="/settings" data-once-submit="1">
                    <?= Session::csrfField() ?>
                    <input type="hidden" name="form_mode" value="pause">
                    <input type="hidden" name="is_paused" value="0">
                    <button type="submit" class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition <?= !$isPaused ? 'bg-tea-600 text-white shadow-sm' : 'text-slate-600 hover:bg-white' ?>">ON</button>
                </form>
                <form method="post" action="/settings" data-once-submit="1">
                    <?= Session::csrfField() ?>
                    <input type="hidden" name="form_mode" value="pause">
                    <input type="hidden" name="is_paused" value="1">
                    <button type="submit" class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition <?= $isPaused ? 'bg-rose-600 text-white shadow-sm' : 'text-slate-600 hover:bg-white' ?>">OFF</button>
                </form>
            </div>
        </div>

        <div class="rounded-2xl bg-tea-50 border border-tea-200 p-3.5 text-xs text-tea-800 flex items-start gap-2">
            <i class="ti ti-info-circle text-base shrink-0 mt-0.5"></i>
            <span>Both Morning (<?= e($morningTimeLabel) ?>) and Afternoon (<?= e($afternoonTimeLabel) ?>) Tea are enabled automatically by default. Uncheck a session to opt out permanently, or skip individual sessions from the Today tab.</span>
        </div>

        <form method="post" action="/settings" class="space-y-4" data-once-submit="1" data-require-online="1">
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
                <div class="card p-4 space-y-3.5">
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

                    <div class="grid grid-cols-3 gap-1.5" data-seg-group>
                        <?php foreach (['normal' => __('sugar_normal'), 'less' => __('sugar_less'), 'none' => __('sugar_none')] as $sk => $sl): ?>
                            <label class="btn-touch border cursor-pointer text-xs px-2 whitespace-nowrap <?= $sug === $sk ? 'bg-tea-600 text-white border-tea-600' : 'bg-white text-slate-700 border-stone-200' ?>">
                                <input type="radio" name="<?= $key ?>_sugar" value="<?= $sk ?>" class="sr-only" <?= $sug === $sk ? 'checked' : '' ?>>
                                <?= e($sl) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs font-semibold text-slate-600"><?= e(__('quantity')) ?></span>
                        <div class="flex items-center gap-2">
                            <button type="button" data-step-delta="-1" class="w-10 h-10 rounded-xl border border-stone-300 bg-white font-bold text-lg inline-flex items-center justify-center"><i class="ti ti-minus"></i></button>
                            <input type="number" name="<?= $key ?>_quantity" min="1" max="5" value="<?= $qty ?>" class="w-12 h-10 text-center font-bold rounded-xl border border-stone-300 bg-white">
                            <button type="button" data-step-delta="1" class="w-10 h-10 rounded-xl border border-stone-300 bg-white font-bold text-lg inline-flex items-center justify-center"><i class="ti ti-plus"></i></button>
                        </div>
                    </div>

                    <div>
                        <label class="label-text text-xs" for="<?= $key ?>_note"><?= e(__('note')) ?></label>
                        <input id="<?= $key ?>_note" type="text" name="<?= $key ?>_note" value="<?= e($note) ?>" maxlength="180" class="input-field text-sm" placeholder="<?= e(__('note_placeholder')) ?>">
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn-primary w-full md:w-auto md:px-8 text-sm md:text-base"><?= e(__('save_as_default')) ?></button>
        </form>
    </section>

    <!-- TAB 2: Profile, Personal Cup Photo & Security -->
    <section id="settings-panel-profile" data-settings-panel="profile" class="space-y-4 <?= $curTab === 'profile' ? '' : 'hidden' ?>">
        <div class="card p-5 space-y-4">
            <div class="flex items-center gap-4">
                <img src="<?= e(cup_image_url($user['cup_image'] ?? null, (int)$user['id'])) ?>" alt="Cup Photo" class="w-16 h-16 md:w-20 md:h-20 rounded-2xl object-cover border border-stone-200 bg-tea-50 shrink-0">
                <div class="min-w-0">
                    <div class="font-bold text-base text-slate-800 truncate"><?= e($user['full_name']) ?></div>
                    <div class="text-xs font-semibold text-tea-700"><?= e($user['employee_id']) ?> &bull; <?= e(ucfirst($user['role'])) ?></div>
                    <div class="text-xs text-slate-500 mt-0.5 truncate"><?= e($user['department_name'] ?? '-') ?> &bull; <?= e($user['room_name'] ?? '-') ?></div>
                </div>
            </div>

            <form method="post" action="/settings" enctype="multipart/form-data" class="space-y-4 pt-3 border-t border-stone-100" data-once-submit="1">
                <?= Session::csrfField() ?>
                <input type="hidden" name="form_mode" value="profile">

                <div>
                    <label class="label-text" for="full_name"><?= e(__('full_name')) ?></label>
                    <input id="full_name" name="full_name" type="text" required value="<?= e($user['full_name']) ?>" class="input-field">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
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
                            <option value="" <?= empty($user['room_id']) ? 'selected' : '' ?>>Unassigned / Select Room</option>
                            <?php foreach ($rooms as $r): ?>
                                <option value="<?= (int)$r['id'] ?>" <?= (int)$user['room_id'] === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php if (!$canEditDeptRoom): ?>
                    <p class="text-[11px] text-slate-400 -mt-2">Department and room updates are managed by Admin.</p>
                <?php endif; ?>

                <div>
                    <div class="flex items-center gap-1.5 mb-1.5">
                        <label class="text-sm font-medium text-slate-700" for="cup_photo"><?= e(__('cup_photo')) ?></label>
                        <div class="relative inline-flex items-center group" id="cup-tip-wrap">
                            <button type="button"
                                    id="cup-photo-tip-btn"
                                    aria-label="Cup photo recommendations"
                                    aria-expanded="false"
                                    class="w-5 h-5 rounded-full bg-tea-50 border border-tea-200 text-tea-700 hover:bg-tea-100 inline-flex items-center justify-center transition focus:outline-none focus:ring-2 focus:ring-tea-500/30">
                                <i class="ti ti-info-circle text-xs"></i>
                            </button>
                            <div id="cup-photo-tooltip"
                                 role="tooltip"
                                 class="hidden group-hover:block group-focus-within:block absolute left-0 sm:left-1/2 sm:-translate-x-1/2 bottom-full mb-2 w-64 rounded-xl bg-slate-800 text-white text-[11px] leading-relaxed p-3 shadow-xl z-30">
                                <div class="font-bold text-amber-300 mb-1 flex items-center gap-1">
                                    <i class="ti ti-aspect-ratio text-xs"></i>
                                    <span>Square 500 &times; 500 px Ideal</span>
                                </div>
                                Please upload a clear square (1:1) photo of your personal mug or cup taken in good lighting. Tea staff enlarge this photo on their screen so cups are never mixed up! (JPG, PNG, or WEBP up to 5 MB.)
                            </div>
                        </div>
                    </div>
                    <input id="cup_photo" name="cup_photo" type="file" accept="image/jpeg,image/png,image/webp" class="w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-tea-50 file:text-tea-700">
                </div>

                <div>
                    <label class="label-text" for="new_password">Change Password (Optional)</label>
                    <input id="new_password" name="new_password" type="password" minlength="6" class="input-field" placeholder="Leave blank to keep current password">
                </div>

                <button type="submit" class="btn-primary w-full md:w-auto md:px-8"><?= e(__('save')) ?></button>
            </form>

            <form method="post" action="/logout" class="pt-3 border-t border-stone-100 md:hidden">
                <?= Session::csrfField() ?>
                <button type="submit" class="btn-outline w-full text-rose-600 border-rose-200 hover:bg-rose-50 inline-flex items-center justify-center gap-1.5">
                    <i class="ti ti-logout text-base"></i>
                    <span><?= e(__('logout')) ?></span>
                </button>
            </form>
        </div>
    </section>

    <!-- TAB 3: Companion & Fun Extras -->
    <section id="settings-panel-fun" data-settings-panel="fun" class="space-y-4 <?= $curTab === 'fun' ? '' : 'hidden' ?>">
        <div class="card p-5 space-y-4">
            <div class="flex items-center justify-between gap-3">
                <div class="font-bold text-sm md:text-base text-slate-800 flex items-center gap-1.5">
                    <i class="ti ti-mood-smile text-amberwarm-600 text-lg"></i>
                    <span>Funny Tea Monkey: <?= $monkeyOn ? 'ON' : 'OFF' ?></span>
                </div>
                <div class="inline-flex rounded-xl border border-stone-200 bg-stone-100 p-1 shrink-0 gap-1">
                    <form method="post" action="/settings" data-once-submit="1" onsubmit="try{localStorage.removeItem('tc_monkey_dismissed_v1');localStorage.removeItem('tc_monkey_next_visit_v1')}catch(e){}">
                        <?= Session::csrfField() ?>
                        <input type="hidden" name="form_mode" value="monkey_pref">
                        <input type="hidden" name="show_funny_monkey" value="1">
                        <button type="submit" class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition <?= ($monkeyOn && !$monkeyDismissedToday) ? 'bg-tea-600 text-white shadow-sm' : 'text-slate-600 hover:bg-white' ?>">ON</button>
                    </form>
                    <form method="post" action="/settings" data-once-submit="1">
                        <?= Session::csrfField() ?>
                        <input type="hidden" name="form_mode" value="monkey_pref">
                        <input type="hidden" name="show_funny_monkey" value="0">
                        <button type="submit" class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition <?= !$monkeyOn ? 'bg-rose-600 text-white shadow-sm' : 'text-slate-600 hover:bg-white' ?>">OFF</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
function switchSettingsTab(tabKey) {
    document.querySelectorAll('[data-settings-tab]').forEach(function (btn) {
        var active = btn.getAttribute('data-settings-tab') === tabKey;
        btn.className = 'min-h-[40px] px-2 py-2 rounded-xl text-xs sm:text-sm font-bold flex items-center justify-center whitespace-nowrap transition ' +
            (active ? 'bg-tea-600 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-100');
    });
    document.querySelectorAll('[data-settings-panel]').forEach(function (panel) {
        panel.classList.toggle('hidden', panel.getAttribute('data-settings-panel') !== tabKey);
    });
    if (window.history && window.history.replaceState) {
        var url = new URL(window.location.href);
        url.searchParams.set('tab', tabKey);
        window.history.replaceState(null, '', url.toString());
    }
}

(function () {
    var tipBtn = document.getElementById('cup-photo-tip-btn');
    var tipBox = document.getElementById('cup-photo-tooltip');
    var tipWrap = document.getElementById('cup-tip-wrap');
    if (tipBtn && tipBox && tipWrap) {
        tipBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var isHidden = tipBox.classList.contains('hidden');
            tipBox.classList.toggle('hidden', !isHidden);
            tipBtn.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
        });
        document.addEventListener('click', function (e) {
            if (!tipWrap.contains(e.target) && !tipBox.classList.contains('hidden')) {
                tipBox.classList.add('hidden');
                tipBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }
})();
</script>
