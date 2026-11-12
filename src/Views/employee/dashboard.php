<?php
use App\Core\Lang;
use App\Core\Session;
use App\Services\AnnouncementService;

$title = __('today');
$dateObj = new DateTimeImmutable($today);
$slotConfigs = [
    'morning'   => ['title' => Lang::current() === 'bn' ? ($morning['slot']['label_bn'] ?? __('morning')) : ($morning['slot']['label_en'] ?? __('morning')), 'icon' => 'ti-sun', 'data' => $morning],
    'afternoon' => ['title' => Lang::current() === 'bn' ? ($afternoon['slot']['label_bn'] ?? __('afternoon')) : ($afternoon['slot']['label_en'] ?? __('afternoon')), 'icon' => 'ti-clock', 'data' => $afternoon],
];
$announcements = $announcements ?? [];
?>
<!-- Header Greeting & Desktop Quick Actions -->
<section class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
    <div>
        <div class="text-xs font-semibold uppercase tracking-wide text-tea-600"><?= e($dateObj->format('l, d M Y')) ?></div>
        <h2 class="text-xl md:text-2xl font-bold text-slate-800">Hello, <?= e(explode(' ', $user['full_name'])[0]) ?></h2>
        <div class="text-xs md:text-sm text-slate-500 flex items-center gap-1.5 mt-0.5">
            <i class="ti ti-building text-slate-400"></i>
            <span><?= e($user['department_name'] ?? 'No Dept') ?></span>
            <span>&bull;</span>
            <i class="ti ti-door text-slate-400"></i>
            <span><?= e($user['room_name'] ?? 'No Room') ?></span>
        </div>
    </div>
    <?php if ($canGuestOrder): ?>
        <div class="hidden md:flex flex-wrap items-center gap-2">
            <a href="/guest-order" class="btn-outline text-sm py-2.5 px-3.5 border-amberwarm-400/50 bg-amberwarm-50 text-amberwarm-600">
                <i class="ti ti-users text-base"></i>
                <span><?= e(__('guest_order')) ?></span>
            </a>
        </div>
    <?php endif; ?>
</section>

<!-- Active Staff Announcements (Unavailable Today / Time Adjustment) -->
<?php if (!empty($announcements)): ?>
    <section class="space-y-2.5">
        <?php foreach ($announcements as $ann):
            $scopeTxt = match ($ann['scope']) {
                'morning'   => 'Morning Session',
                'afternoon' => 'Afternoon Session',
                default     => 'All Day',
            };
            $isUnavailable = $ann['announce_type'] === 'unavailable';
            $isTimeChange = $ann['announce_type'] === 'time_change';
            $boxClass = $isUnavailable
                ? 'bg-rose-50 border-rose-300 text-rose-800'
                : ($isTimeChange ? 'bg-amberwarm-100 border-amberwarm-400/60 text-amberwarm-600' : 'bg-tea-50 border-tea-300 text-tea-800');
            $iconClass = $isUnavailable ? 'ti-alert-triangle' : ($isTimeChange ? 'ti-clock-edit' : 'ti-speakerphone');
            $headline = $isUnavailable
                ? "Tea / Coffee Unavailable ({$scopeTxt})"
                : ($isTimeChange
                    ? "Serving Time Adjusted ({$scopeTxt}): " . format_time_12((string)$ann['adjusted_time'])
                    : "Staff Notice ({$scopeTxt})");
        ?>
            <div class="rounded-2xl border p-4 flex items-start gap-3 shadow-sm <?= $boxClass ?>">
                <div class="w-10 h-10 rounded-xl bg-white/80 flex items-center justify-center text-xl shrink-0">
                    <i class="ti <?= $iconClass ?>"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="font-bold text-sm md:text-base leading-snug"><?= e($headline) ?></div>
                    <div class="text-xs md:text-sm mt-0.5 opacity-95"><?= e($ann['reason']) ?></div>
                    <div class="text-[11px] opacity-75 mt-1">Posted by <?= e($ann['author_name'] ?? 'Tea Staff') ?> &bull; <?= e(date('g:i A', strtotime($ann['created_at']))) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<!-- Day Status / Away Alerts -->
<?php if (!$dayStatus['is_open']): ?>
    <div class="rounded-2xl bg-amberwarm-100 border border-amberwarm-400/40 p-3.5 text-sm font-semibold text-amberwarm-600 flex items-center gap-2">
        <i class="ti ti-calendar-off text-lg shrink-0"></i>
        <span><?= $dayStatus['reason'] === 'holiday' ? e(__('holiday_banner', ['title' => $dayStatus['holiday']['title'] ?? 'Holiday'])) : e(__('non_working_banner')) ?></span>
    </div>
<?php elseif ((int)$user['is_paused'] === 1): ?>
    <div class="rounded-2xl bg-amberwarm-100 border border-amberwarm-400/40 p-3.5 flex items-center justify-between gap-2 text-xs md:text-sm font-semibold text-amberwarm-600">
        <span class="inline-flex items-center gap-1.5">
            <i class="ti ti-power text-base"></i>
            <span><?= e(__('paused_badge')) ?></span>
        </span>
        <form method="post" action="/settings" class="shrink-0" data-once-submit="1">
            <?= Session::csrfField() ?>
            <input type="hidden" name="form_mode" value="pause">
            <input type="hidden" name="is_paused" value="0">
            <input type="hidden" name="redirect_to" value="/today">
            <button type="submit" class="px-3 py-1 rounded-lg bg-tea-600 text-white text-xs font-bold hover:bg-tea-700 transition">Turn ON</button>
        </form>
    </div>
<?php endif; ?>

<!-- Mobile Dual Order Actions (On-Demand + Guest / Meeting) -->
<div class="md:hidden grid <?= $canGuestOrder ? 'grid-cols-2' : 'grid-cols-1' ?> gap-2.5">
    <a href="/order-now" class="btn-amber w-full text-[11px] sm:text-xs font-bold py-3 px-2 shadow-sm text-center leading-tight inline-flex items-center justify-center gap-1">
        <i class="ti ti-bolt text-base shrink-0"></i>
        <span>On-Demand Order</span>
    </a>
    <?php if ($canGuestOrder): ?>
        <a href="/guest-order" class="btn-outline w-full text-[11px] sm:text-xs font-bold py-3 px-2 border-amberwarm-400/60 bg-amberwarm-50 text-amberwarm-600 shadow-sm text-center leading-tight inline-flex items-center justify-center gap-1">
            <i class="ti ti-users text-base shrink-0"></i>
            <span><?= e(__('guest_order')) ?></span>
        </a>
    <?php endif; ?>
</div>

<!-- Main Responsive 24" Desktop Grid / Mobile Single Stack -->
<div id="employee-live-root" data-employee-live="1" class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
    <?php if (!empty($todayRequests)): ?>
        <!-- First on Mobile (Top) & First Column on Desktop (Left) whenever Live Requests exist -->
        <aside id="employee-live-requests" class="lg:col-span-4 space-y-4">
            <section class="space-y-2.5">
                <h3 class="text-sm font-bold text-slate-700 flex items-center justify-between gap-2">
                    <span class="inline-flex items-center gap-1.5">
                        <i class="ti ti-bolt text-amberwarm-600"></i>
                        <span>Today's Live Requests</span>
                    </span>
                    <span class="text-[11px] font-semibold text-emerald-700 inline-flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>Live
                    </span>
                </h3>
                <?php foreach ($todayRequests as $req):
                    $reqStatus = $req['status'];
                    $estMins = !empty($req['estimated_minutes']) ? (int)$req['estimated_minutes'] : 0;
                ?>
                    <div class="card p-3.5 space-y-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <div class="text-xs font-bold uppercase tracking-wide inline-flex items-center gap-1 <?= $req['order_type'] === 'guest' ? 'text-amberwarm-600' : 'text-tea-700' ?>">
                                <i class="ti <?= $req['order_type'] === 'guest' ? 'ti-users' : 'ti-bolt' ?> text-sm"></i>
                                <span><?= $req['order_type'] === 'guest' ? 'Guest Order' : 'On-Demand' ?> &bull; <?= e(date('g:i A', strtotime($req['created_at']))) ?></span>
                            </div>
                            <?php $status = $reqStatus; $estimatedMinutes = $estMins; require __DIR__ . '/../partials/status-badge.php'; ?>
                        </div>
                        <div class="text-sm font-semibold text-slate-800 flex flex-wrap items-center gap-1.5">
                            <span>
                                <?php foreach ($req['items'] as $idx => $it): ?>
                                    <?= $idx > 0 ? ', ' : '' ?><?= (int)$it['quantity'] ?>&times; <?= e(badge_beverage($it['beverage'])) ?> (<?= e(badge_sugar($it['sugar'])) ?>)
                                <?php endforeach; ?>
                            </span>
                            <?php if (!empty($req['requested_within'])): ?>
                                <span class="px-2 py-0.5 rounded-md bg-sky-50 text-sky-700 border border-sky-200 text-[10px] font-bold">
                                    <?= e($req['requested_within']) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if ($reqStatus === 'almost_done'): ?>
                            <div class="rounded-xl bg-teal-50 border border-teal-200 px-3 py-1.5 text-xs font-semibold text-teal-800 flex items-center gap-1.5">
                                <i class="ti ti-hourglass-high text-sm shrink-0"></i>
                                <span>Almost done<?= !empty($req['accepted_by_name']) ? ' (' . e($req['accepted_by_name']) . ')' : '' ?>! Ready in ~<?= $estMins > 0 ? $estMins : 5 ?> min</span>
                            </div>
                        <?php elseif (in_array($reqStatus, ['accepted', 'preparing'], true)): ?>
                            <div class="rounded-xl bg-indigo-50 border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-700 flex items-center gap-1.5">
                                <i class="ti ti-flame text-sm shrink-0 animate-pulse"></i>
                                <span><strong><?= e(!empty($req['accepted_by_name']) ? $req['accepted_by_name'] : 'Tea Staff') ?></strong> accepted and is preparing your order...</span>
                            </div>
                        <?php elseif ($reqStatus === 'pending'): ?>
                            <div class="rounded-xl bg-amberwarm-50 border border-amberwarm-400/30 px-3 py-1.5 text-xs font-medium text-amberwarm-600 flex items-center gap-1.5">
                                <i class="ti ti-clock text-sm shrink-0"></i>
                                <span>Waiting for Tea Staff to accept...</span>
                            </div>
                        <?php elseif ($reqStatus === 'system_cancelled'): ?>
                            <div class="rounded-xl bg-stone-100 border border-stone-300 px-3 py-1.5 text-xs font-medium text-slate-600 flex items-center gap-1.5">
                                <i class="ti ti-clock-x text-sm shrink-0"></i>
                                <span>System auto-cancelled after 3 hours without completion.</span>
                            </div>
                        <?php endif; ?>

                        <div class="text-xs text-slate-500 flex flex-wrap items-center justify-between gap-2 pt-1 border-t border-stone-100">
                            <span class="inline-flex items-center gap-1 min-w-0">
                                <i class="ti ti-map-pin text-sm shrink-0"></i>
                                <span class="truncate"><?= e($req['room_name_snapshot']) ?><?= !empty($req['meeting_title']) ? ' — ' . e($req['meeting_title']) : '' ?></span>
                            </span>
                            <?php if ($reqStatus === 'pending'): ?>
                                <form method="post" action="/orders/cancel" data-ajax-action="1" onsubmit="return confirm('Cancel this pending order?');">
                                    <?= Session::csrfField() ?>
                                    <input type="hidden" name="order_id" value="<?= (int)$req['id'] ?>">
                                    <button type="submit" class="px-2.5 py-1 rounded-lg border border-rose-200 bg-rose-50 text-rose-600 font-bold text-xs hover:bg-rose-100 transition inline-flex items-center gap-1">
                                        <i class="ti ti-x text-xs"></i>Cancel
                                    </button>
                                </form>
                            <?php elseif (in_array($reqStatus, ['accepted', 'preparing', 'almost_done'], true)): ?>
                                <form method="post" action="/orders/deliver" data-ajax-action="1">
                                    <?= Session::csrfField() ?>
                                    <input type="hidden" name="order_id" value="<?= (int)$req['id'] ?>">
                                    <button type="submit" class="px-2.5 py-1 rounded-lg bg-emerald-600 text-white font-bold text-xs hover:bg-emerald-700 transition inline-flex items-center gap-1 shadow-sm">
                                        <i class="ti ti-circle-check text-xs"></i>Mark Delivered
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>
        </aside>
    <?php endif; ?>

    <!-- Morning & Afternoon Session Cards Side-by-Side on Tablet/Desktop -->
    <section id="employee-slots-grid" class="<?= !empty($todayRequests) ? 'lg:col-span-8' : 'lg:col-span-12' ?> grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php foreach ($slotConfigs as $slotKey => $cfg):
            $s = $cfg['data'];
            $slot = $s['slot'];
            $eff = $s['effective'];
            $state = $s['state'];
            $isLocked = $s['is_locked'];
            $serviceTimeStr = format_time_12($slot['service_time'] ?? '');
            $cutoffTimeStr = format_time_12($slot['cutoff_time'] ?? '');
            $editId = 'edit-' . $slotKey;
            $slotNotice = AnnouncementService::findSlotNotice($announcements, $slotKey);
        ?>
        <div class="card p-3.5 sm:p-4 md:p-5 space-y-3 flex flex-col justify-between">
            <div class="space-y-3">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-2 sm:gap-2.5 min-w-0 flex-1">
                        <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-tea-50 text-tea-700 flex items-center justify-center text-lg sm:text-xl shrink-0">
                            <i class="ti <?= $cfg['icon'] ?>"></i>
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-bold text-slate-800 text-base leading-tight truncate"><?= e($cfg['title']) ?></h3>
                            <div class="text-[11px] sm:text-xs text-slate-500 mt-0.5 whitespace-nowrap"><?= e($serviceTimeStr) ?> &bull; <?= e(__('cutoff_at', ['time' => $cutoffTimeStr])) ?></div>
                        </div>
                    </div>

                    <?php if ($state === 'finalized' && $s['finalized_order']): ?>
                        <?php $status = $s['finalized_order']['status']; require __DIR__ . '/../partials/status-badge.php'; ?>
                    <?php elseif ($state === 'skipped'): ?>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-600 border border-rose-200 inline-flex items-center gap-1">
                            <i class="ti ti-player-skip-forward text-xs"></i><?= e(__('skipped')) ?>
                        </span>
                    <?php elseif ($state === 'override'): ?>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amberwarm-100 text-amberwarm-600">Today Override</span>
                    <?php elseif ($state === 'default'): ?>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-tea-100 text-tea-700 inline-flex items-center gap-1">
                            <i class="ti ti-check text-xs"></i>Auto <?= e(__('scheduled')) ?>
                        </span>
                    <?php elseif ($state === 'paused'): ?>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-600 border border-rose-200 inline-flex items-center gap-1">
                            <i class="ti ti-power text-xs"></i>Orders OFF
                        </span>
                    <?php elseif ($state === 'holiday'): ?>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-stone-100 text-slate-600 inline-flex items-center gap-1">
                            <i class="ti ti-calendar-off text-xs"></i>Holiday
                        </span>
                    <?php elseif ($state === 'non_working'): ?>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-stone-100 text-slate-600 inline-flex items-center gap-1">
                            <i class="ti ti-calendar-minus text-xs"></i>Day Off
                        </span>
                    <?php else: ?>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-stone-100 text-slate-500">Disabled</span>
                    <?php endif; ?>
                </div>

                <?php if ($slotNotice): ?>
                    <div class="rounded-xl px-3 py-2 text-xs font-semibold flex items-center gap-1.5 <?= $slotNotice['announce_type'] === 'unavailable' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-amberwarm-50 text-amberwarm-600 border border-amberwarm-400/40' ?>">
                        <i class="ti <?= $slotNotice['announce_type'] === 'unavailable' ? 'ti-ban' : 'ti-clock-edit' ?> text-sm shrink-0"></i>
                        <span>
                            <?php if ($slotNotice['announce_type'] === 'time_change'): ?>
                                Today shifted to <?= e(format_time_12((string)$slotNotice['adjusted_time'])) ?> — <?= e($slotNotice['reason']) ?>
                            <?php elseif ($slotNotice['announce_type'] === 'unavailable'): ?>
                                Suspended today: <?= e($slotNotice['reason']) ?>
                            <?php else: ?>
                                <?= e($slotNotice['reason']) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>

                <?php if ($eff): ?>
                    <div class="rounded-xl bg-tea-50/50 border border-tea-200/60 p-2.5 sm:p-3 flex items-center justify-between gap-2">
                        <div>
                            <div class="font-bold text-slate-800 flex items-center gap-1.5">
                                <i class="ti <?= $eff['beverage'] === 'coffee' ? 'ti-coffee text-amberwarm-600' : 'ti-cup text-tea-700' ?> text-lg"></i>
                                <span><?= (int)$eff['quantity'] ?> &times; <?= e(badge_beverage($eff['beverage'])) ?></span>
                                <span class="text-xs font-medium text-slate-500">&bull; <?= e(badge_sugar($eff['sugar'])) ?></span>
                            </div>
                            <?php if (!empty($eff['note'])): ?>
                                <div class="text-xs text-slate-500 mt-0.5">"<?= e($eff['note']) ?>"</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif ($state === 'skipped'): ?>
                    <p class="text-xs text-slate-500">You skipped this session for today. Tap Reset to Default if you want it back before cutoff.</p>
                <?php elseif ($state === 'paused'): ?>
                    <p class="text-xs text-slate-500">Regular orders are turned OFF.</p>
                <?php elseif ($state === 'holiday'): ?>
                    <p class="text-xs text-slate-500">Office closed for holiday today.</p>
                <?php elseif ($state === 'non_working'): ?>
                    <p class="text-xs text-slate-500">Non-working day today.</p>
                <?php elseif ($state === 'not_configured'): ?>
                    <p class="text-xs text-slate-500">This session is disabled in your <a href="/settings" class="text-tea-700 font-semibold">Settings</a>. Tap Change Today or update Settings to enable.</p>
                <?php endif; ?>
            </div>

            <?php if (!$isLocked): ?>
                <div class="pt-1 space-y-3">
                    <div class="grid <?= ($state !== 'skipped' || $s['override']) ? 'grid-cols-2' : 'grid-cols-1' ?> gap-2">
                        <button type="button" onclick="document.getElementById('<?= $editId ?>').classList.toggle('hidden')" class="btn-outline w-full text-xs py-2.5 px-3 whitespace-nowrap inline-flex items-center justify-center gap-1.5">
                            <i class="ti ti-pencil text-sm shrink-0"></i>
                            <span><?= e(__('change_today')) ?></span>
                        </button>
                        <?php if ($state !== 'skipped'): ?>
                            <form method="post" action="/today/override" class="w-full" data-once-submit="1" data-require-online="1">
                                <?= Session::csrfField() ?>
                                <input type="hidden" name="slot_key" value="<?= e($slotKey) ?>">
                                <input type="hidden" name="action" value="skip">
                                <button type="submit" class="btn-outline w-full text-xs py-2.5 px-3 whitespace-nowrap text-rose-600 border-rose-200 hover:bg-rose-50 inline-flex items-center justify-center gap-1.5">
                                    <i class="ti ti-player-skip-forward text-sm shrink-0"></i>
                                    <span><?= e(__('skip_session')) ?></span>
                                </button>
                            </form>
                        <?php endif; ?>
                        <?php if ($s['override']): ?>
                            <form method="post" action="/today/override" class="<?= $state !== 'skipped' ? 'col-span-2' : '' ?> w-full" data-once-submit="1" data-require-online="1">
                                <?= Session::csrfField() ?>
                                <input type="hidden" name="slot_key" value="<?= e($slotKey) ?>">
                                <input type="hidden" name="action" value="reset">
                                <button type="submit" class="btn-outline w-full text-xs py-2 px-3 whitespace-nowrap inline-flex items-center justify-center gap-1.5">
                                    <i class="ti ti-refresh text-sm shrink-0"></i>
                                    <span><?= e(__('reset_default')) ?></span>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <!-- Inline Today Override Editor -->
                    <form id="<?= $editId ?>" method="post" action="/today/override" class="hidden pt-3 border-t border-stone-100 space-y-3" data-once-submit="1" data-require-online="1" data-offline-msg="<?= e(__('offline_error')) ?>">
                        <?= Session::csrfField() ?>
                        <input type="hidden" name="slot_key" value="<?= e($slotKey) ?>">
                        <input type="hidden" name="action" value="custom">
                        <div class="text-xs font-semibold text-amberwarm-600">Changes apply to TODAY only (your recurring default stays intact).</div>

                        <?php
                        $curBev = $eff['beverage'] ?? 'tea';
                        $curSug = $eff['sugar'] ?? 'normal';
                        $curQty = (int)($eff['quantity'] ?? 1);
                        $curNote = $eff['note'] ?? '';
                        ?>

                        <div class="grid grid-cols-2 gap-2" data-seg-group>
                            <label class="btn-touch border cursor-pointer text-sm <?= $curBev === 'tea' ? 'bg-tea-600 text-white border-tea-600' : 'bg-white text-slate-700 border-stone-200' ?>">
                                <input type="radio" name="beverage" value="tea" class="sr-only" <?= $curBev === 'tea' ? 'checked' : '' ?>>
                                <i class="ti ti-cup text-base"></i> <?= e(__('tea')) ?>
                            </label>
                            <label class="btn-touch border cursor-pointer text-sm <?= $curBev === 'coffee' ? 'bg-tea-600 text-white border-tea-600' : 'bg-white text-slate-700 border-stone-200' ?>">
                                <input type="radio" name="beverage" value="coffee" class="sr-only" <?= $curBev === 'coffee' ? 'checked' : '' ?>>
                                <i class="ti ti-coffee text-base"></i> <?= e(__('coffee')) ?>
                            </label>
                        </div>

                        <div class="grid grid-cols-3 gap-2" data-seg-group>
                            <?php foreach (['normal' => __('sugar_normal'), 'less' => __('sugar_less'), 'none' => __('sugar_none')] as $sk => $sl): ?>
                                <label class="btn-touch border cursor-pointer text-xs px-2 <?= $curSug === $sk ? 'bg-tea-600 text-white border-tea-600' : 'bg-white text-slate-700 border-stone-200' ?>">
                                    <input type="radio" name="sugar" value="<?= $sk ?>" class="sr-only" <?= $curSug === $sk ? 'checked' : '' ?>>
                                    <?= e($sl) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs font-semibold text-slate-600"><?= e(__('quantity')) ?></span>
                            <div class="flex items-center gap-2">
                                <button type="button" data-step-delta="-1" class="w-11 h-11 rounded-xl border border-stone-300 bg-white font-bold text-lg inline-flex items-center justify-center"><i class="ti ti-minus"></i></button>
                                <input type="number" name="quantity" min="1" max="5" value="<?= $curQty ?>" class="w-14 h-11 text-center font-bold rounded-xl border border-stone-300 bg-white">
                                <button type="button" data-step-delta="1" class="w-11 h-11 rounded-xl border border-stone-300 bg-white font-bold text-lg inline-flex items-center justify-center"><i class="ti ti-plus"></i></button>
                            </div>
                        </div>

                        <div>
                            <input type="text" name="note" value="<?= e($curNote) ?>" maxlength="180" class="input-field text-sm" placeholder="<?= e(__('note_placeholder')) ?>">
                        </div>

                        <button type="submit" class="btn-primary w-full text-sm">Save for Today Only</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="text-xs text-slate-500 flex items-center pt-2 border-t border-stone-100">
                    <span class="inline-flex items-center gap-1"><i class="ti ti-lock text-sm"></i><?= e(__('locked')) ?></span>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </section>
</div>
