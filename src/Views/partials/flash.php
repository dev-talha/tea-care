<?php
use App\Core\Session;
$flashes = Session::getFlashes();
?>
<style>
#tc-alert-stack {
    position: fixed;
    top: max(0.5rem, env(safe-area-inset-top));
    left: 0.75rem;
    right: 0.75rem;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    pointer-events: none;
}
@media (min-width: 640px) {
    #tc-alert-stack {
        left: auto;
        top: 1rem;
        right: 1.25rem;
        width: 100%;
        max-width: 24rem;
    }
}
.tc-alert-item {
    pointer-events: auto;
    position: relative;
    overflow: hidden;
    border-radius: 0.875rem;
    border: 1px solid #cbd5e1;
    box-shadow: 0 12px 28px -6px rgba(15, 23, 42, 0.16), 0 4px 10px -4px rgba(15, 23, 42, 0.08);
    animation: tcAlertIn 0.22s ease-out forwards;
    transition: opacity 0.22s ease, transform 0.22s ease;
}
.tc-alert-item.is-closing {
    opacity: 0;
    transform: translateY(-8px);
}
.tc-alert-success {
    background: #ecfdf5;
    border-color: #a7f3d0;
    color: #065f46;
}
.tc-alert-warning {
    background: #fffbeb;
    border-color: #fde68a;
    color: #92400e;
}
.tc-alert-info {
    background: #f0f9ff;
    border-color: #bae6fd;
    color: #075985;
}
.tc-alert-error {
    background: #fff1f2;
    border-color: #fecdd3;
    color: #9f1239;
}
.tc-alert-row {
    display: flex;
    align-items: flex-start;
    gap: 0.625rem;
    padding: 0.75rem 0.75rem 0.85rem 0.875rem;
}
.tc-alert-icon {
    font-size: 1.15rem;
    line-height: 1.25rem;
    flex-shrink: 0;
    margin-top: 0.05rem;
}
.tc-alert-msg {
    flex: 1 1 auto;
    min-width: 0;
    font-size: 0.8125rem;
    line-height: 1.35;
    font-weight: 600;
    word-break: break-word;
}
.tc-alert-close {
    flex-shrink: 0;
    width: 1.625rem;
    height: 1.625rem;
    border-radius: 0.5rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 0;
    background: transparent;
    color: currentColor;
    opacity: 0.65;
    cursor: pointer;
    transition: opacity 0.15s ease, background-color 0.15s ease;
}
.tc-alert-close:hover {
    opacity: 1;
    background: rgba(15, 23, 42, 0.08);
}
.tc-alert-bar {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    height: 3px;
    background: rgba(15, 23, 42, 0.08);
}
.tc-alert-progress {
    display: block;
    height: 100%;
    width: 100%;
    transform-origin: left center;
    animation: tcAlertProgress 10000ms linear forwards;
}
.tc-alert-item:hover .tc-alert-progress {
    animation-play-state: paused;
}
.tc-alert-success .tc-alert-progress { background: #059669; }
.tc-alert-warning .tc-alert-progress { background: #d97706; }
.tc-alert-info .tc-alert-progress { background: #0284c7; }
.tc-alert-error .tc-alert-progress { background: #e11d48; }
@keyframes tcAlertIn {
    from { opacity: 0; transform: translateY(-10px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes tcAlertProgress {
    from { transform: scaleX(1); }
    to   { transform: scaleX(0); }
}
</style>
<div id="tc-alert-stack" role="region" aria-live="polite" aria-label="Notifications">
<?php foreach ($flashes as $f):
    $rawType = (string)($f['type'] ?? 'info');
    $normType = match ($rawType) {
        'success'         => 'success',
        'warning'         => 'warning',
        'info'            => 'info',
        default           => 'error',
    };
    $iconCls = match ($normType) {
        'success' => 'ti-circle-check',
        'warning' => 'ti-alert-triangle',
        'info'    => 'ti-info-circle',
        default   => 'ti-alert-circle',
    };
?>
    <div class="tc-alert-item tc-alert-<?= $normType ?>" role="alert" data-duration="10000">
        <div class="tc-alert-row">
            <i class="ti <?= $iconCls ?> tc-alert-icon"></i>
            <div class="tc-alert-msg"><?= e($f['message']) ?></div>
            <button type="button" class="tc-alert-close" data-tc-alert-close="1" aria-label="Close alert">
                <i class="ti ti-x"></i>
            </button>
        </div>
        <div class="tc-alert-bar"><span class="tc-alert-progress"></span></div>
    </div>
<?php endforeach; ?>
</div>
