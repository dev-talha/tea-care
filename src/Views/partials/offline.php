<?php $title = 'Offline'; ?>
<div class="card p-6 text-center space-y-3">
    <div class="w-14 h-14 mx-auto rounded-2xl bg-amberwarm-50 text-amberwarm-600 flex items-center justify-center text-3xl">
        <i class="ti ti-wifi-off"></i>
    </div>
    <h2 class="text-lg font-bold text-slate-800">You are Offline</h2>
    <p class="text-sm text-slate-600"><?= e(__('offline_error')) ?></p>
    <a href="/" class="btn-primary w-full">Retry Connection</a>
</div>
