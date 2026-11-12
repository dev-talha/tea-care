<?php
use App\Core\Session;
$flashes = Session::getFlashes();
foreach ($flashes as $f):
    $cls = match ($f['type']) {
        'success' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
        'warning' => 'bg-amberwarm-50 border-amberwarm-400/50 text-amberwarm-600',
        default   => 'bg-rose-50 border-rose-200 text-rose-800',
    };
?>
<div class="rounded-xl border px-4 py-3 text-sm font-medium <?= $cls ?>">
    <?= e($f['message']) ?>
</div>
<?php endforeach; ?>
