<?php
$statusMap = [
    'pending'     => ['bg-amberwarm-100 text-amberwarm-600', __('status_pending'), null],
    'accepted'    => ['bg-indigo-100 text-indigo-700', __('status_preparing'), 'ti-flame'],
    'preparing'   => ['bg-indigo-100 text-indigo-700', __('status_preparing'), 'ti-flame'],
    'almost_done' => ['bg-teal-100 text-teal-800 border border-teal-200', __('status_almost_done'), 'ti-hourglass-high'],
    'delivered'        => ['bg-emerald-100 text-emerald-800 border border-emerald-200', __('status_delivered'), 'ti-check'],
    'cancelled'        => ['bg-rose-100 text-rose-700 border border-rose-200', __('status_cancelled'), null],
    'system_cancelled' => ['bg-slate-100 text-slate-700 border border-slate-300', __('status_system_cancelled'), null],
    'estimated'        => ['bg-sky-50 text-sky-700 border border-sky-200', 'Estimated', null],
];
$curStatus = $status ?? 'pending';
$estMins = isset($estimatedMinutes) ? (int)$estimatedMinutes : 0;
[$cls, $lbl, $ico] = $statusMap[$curStatus] ?? ['bg-stone-100 text-stone-700', $curStatus, null];
if ($curStatus === 'almost_done' && $estMins > 0) {
    $lbl .= ' (~' . $estMins . 'm)';
}
$estimatedMinutes = null;
?>
<span class="inline-flex items-center gap-1 px-2 sm:px-2.5 py-0.5 rounded-full text-xs font-semibold whitespace-nowrap shrink-0 <?= $cls ?>"><?php if ($ico): ?><i class="ti <?= $ico ?> text-xs shrink-0"></i><?php endif; ?><?= e($lbl) ?></span>
