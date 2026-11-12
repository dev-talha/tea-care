<?php
use App\Core\Auth;
use App\Core\Session;

$officeName = setting('office_name', 'TeaCare');
$officeLogo = setting('office_logo', '');
$role = $user['role'] ?? 'employee';
$reqUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isStaffView = str_starts_with($reqUri, '/staff');
$canGuest = Auth::canOrderForGuests() && setting('guest_ordering_enabled', '1') === '1';
$staffTab = $_GET['tab'] ?? 'skipped';
$navUrgentPending = isset($urgentPendingCount)
    ? (int)$urgentPendingCount
    : ($isStaffView ? (int)(\App\Core\Database::fetchOne(
        "SELECT COUNT(*) AS cnt FROM orders WHERE order_date = ? AND order_type IN ('on_demand', 'guest') AND status = 'pending'",
        [\App\Services\ScheduleService::today()]
    )['cnt'] ?? 0) : 0);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#2d6a4f">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title><?= e($title ?? __('app_name')) ?> — <?= e($officeName) ?></title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
    <link rel="icon" type="image/png" href="/assets/icons/icon-192.png">
    <link rel="stylesheet" href="/assets/tabler/tabler-icons.min.css">
    <link rel="stylesheet" href="/assets/css/app.css?v=<?= @filemtime(__DIR__ . '/../../../public/assets/css/app.css') ?: '2' ?>">
</head>
<body class="min-h-screen pb-24 md:pb-10">
    <!-- Top Smooth Loading Bar -->
    <div id="nav-progress" class="fixed top-0 left-0 h-0.5 bg-amberwarm-500 z-[100] transition-all duration-300 opacity-0 w-0 pointer-events-none"></div>

    <!-- Offline Banner -->
    <div id="offline-banner" class="hidden bg-rose-600 text-white text-xs font-semibold text-center py-2 px-4 sticky top-0 z-50 flex items-center justify-center gap-1.5">
        <i class="ti ti-wifi-off text-sm"></i>
        <span><?= e(__('offline_error')) ?></span>
    </div>

    <?php require __DIR__ . '/../partials/flash.php'; ?>

    <!-- Responsive Top App Bar (Mobile App Bar + Desktop 24" Wide Navigation) -->
    <header class="bg-white/95 backdrop-blur border-b border-tea-100 shadow-[0_1px_8px_rgba(45,106,79,0.06)] sticky top-0 z-40">
        <div class="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4 md:px-6 lg:px-8 h-14 md:h-16 flex items-center justify-between gap-3">
            <!-- Brand -->
            <a href="<?= $isStaffView ? '/staff' : '/today' ?>" class="flex items-center gap-2 min-w-0">
                <?php if ($officeLogo !== ''): ?>
                    <img src="/media/logo?v=<?= e(substr(md5($officeLogo), 0, 8)) ?>" alt="Logo" class="h-8 w-8 md:h-9 md:w-9 rounded-lg object-contain">
                <?php else: ?>
                    <img src="/assets/logo.svg" alt="TeaCare" class="h-8 w-8 md:h-9 md:w-9 rounded-xl">
                <?php endif; ?>
                <div class="truncate">
                    <div class="text-sm md:text-base font-bold text-tea-800 leading-tight truncate"><?= e($officeName) ?></div>
                    <div class="text-[11px] text-slate-500 leading-tight truncate md:hidden"><?= e($user['full_name'] ?? '') ?></div>
                    <div class="text-[11px] text-tea-600 font-medium leading-tight hidden md:block"><?= $isStaffView ? 'Staff Operations' : 'Employee Portal' ?></div>
                </div>
            </a>

            <!-- Desktop Navigation (Hidden on mobile where bottom nav handles navigation) -->
            <nav class="hidden md:flex items-center gap-1 overflow-x-auto">
                <?php if ($isStaffView): ?>
                    <a href="/staff" class="px-3 py-2 rounded-xl text-xs font-semibold inline-flex items-center gap-1.5 transition <?= ($activeTab ?? '') !== 'staff_settings' ? 'bg-tea-600 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-100' ?>">
                        <i class="ti ti-layout-dashboard text-sm"></i><span>Operations</span>
                    </a>
                    <a href="/staff/settings" class="px-3 py-2 rounded-xl text-xs font-semibold inline-flex items-center gap-1.5 transition <?= ($activeTab ?? '') === 'staff_settings' ? 'bg-tea-600 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-100' ?>">
                        <i class="ti ti-settings text-sm"></i><span>Settings</span>
                    </a>
                <?php else: ?>
                    <?php $isSettingsRoute = in_array($reqUri, ['/settings', '/defaults', '/profile'], true); ?>
                    <a href="/today" class="px-3 py-2 rounded-xl text-xs font-semibold inline-flex items-center gap-1.5 transition <?= $reqUri === '/today' ? 'bg-tea-600 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-100' ?>">
                        <i class="ti ti-mug text-sm"></i><span><?= e(__('today')) ?></span>
                    </a>
                    <a href="/order-now" class="px-3 py-2 rounded-xl text-xs font-semibold inline-flex items-center gap-1.5 transition <?= $reqUri === '/order-now' ? 'bg-amberwarm-500 text-white shadow-sm' : 'text-amberwarm-600 hover:bg-amberwarm-50' ?>">
                        <i class="ti ti-bolt text-sm"></i><span>Order Now</span>
                    </a>
                    <a href="/history" class="px-3 py-2 rounded-xl text-xs font-semibold inline-flex items-center gap-1.5 transition <?= $reqUri === '/history' ? 'bg-tea-600 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-100' ?>">
                        <i class="ti ti-history text-sm"></i><span><?= e(__('history')) ?></span>
                    </a>
                    <?php if ($canGuest): ?>
                        <a href="/guest-order" class="px-3 py-2 rounded-xl text-xs font-semibold inline-flex items-center gap-1.5 transition <?= $reqUri === '/guest-order' ? 'bg-tea-600 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-100' ?>">
                            <i class="ti ti-users-group text-sm"></i><span>Guest Order</span>
                        </a>
                    <?php endif; ?>
                    <a href="/settings" class="px-3 py-2 rounded-xl text-xs font-semibold inline-flex items-center gap-1.5 transition <?= $isSettingsRoute ? 'bg-tea-600 text-white shadow-sm' : 'text-slate-600 hover:bg-stone-100' ?>">
                        <i class="ti ti-settings text-sm"></i><span>Settings</span>
                    </a>
                <?php endif; ?>
            </nav>

            <!-- Right Controls -->
            <div class="flex items-center gap-1.5 shrink-0">
                <?php if ($role === 'admin'): ?>
                    <a href="/admin" class="px-2.5 py-1.5 rounded-lg bg-tea-50 text-tea-700 text-xs font-semibold border border-tea-200 inline-flex items-center gap-1">
                        <i class="ti ti-shield text-sm"></i><span>Admin</span>
                    </a>
                <?php endif; ?>
                <?php if ($role === 'staff' || $role === 'admin'): ?>
                    <?php if ($isStaffView): ?>
                        <button type="button" id="staff-emp-search-btn" title="Search Employee" aria-label="Search Employee" class="px-2.5 py-1.5 rounded-lg bg-stone-100 hover:bg-tea-50 text-slate-700 hover:text-tea-700 border border-stone-200 text-xs font-semibold inline-flex items-center gap-1.5 transition shrink-0">
                            <i class="ti ti-search text-base"></i><span class="hidden lg:inline">Search</span>
                        </button>
                        <a href="/today" class="px-2.5 py-1.5 rounded-lg bg-cream-100 text-slate-700 text-xs font-semibold border border-stone-200 inline-flex items-center gap-1 shrink-0">
                            <i class="ti ti-mug text-sm"></i><span>My Tea</span>
                        </a>
                        <a href="/staff/settings" title="Staff Settings" aria-label="Staff Settings" class="md:hidden px-2.5 py-1.5 rounded-lg <?= ($activeTab ?? '') === 'staff_settings' ? 'bg-tea-600 text-white border-tea-600' : 'bg-stone-100 text-slate-700 border-stone-200' ?> border text-xs font-semibold inline-flex items-center justify-center shrink-0">
                            <i class="ti ti-settings text-base"></i>
                        </a>
                    <?php else: ?>
                        <a href="/staff" class="px-2.5 py-1.5 rounded-lg bg-amberwarm-100 text-amberwarm-600 text-xs font-semibold border border-amberwarm-400/40 inline-flex items-center gap-1">
                            <i class="ti ti-list-check text-sm"></i><span>Staff</span>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (!$isStaffView): ?>
                    <a href="/settings" title="Settings" aria-label="Settings" class="md:hidden px-2.5 py-1.5 rounded-lg <?= in_array($reqUri, ['/settings', '/defaults', '/profile'], true) ? 'bg-tea-600 text-white border-tea-600' : 'bg-stone-100 text-slate-700 border-stone-200' ?> border text-xs font-semibold inline-flex items-center justify-center">
                        <i class="ti ti-settings text-base"></i>
                    </a>
                <?php endif; ?>

                <!-- Desktop User Badge & Logout -->
                <div class="hidden md:flex items-center gap-2 pl-2 ml-1 border-l border-stone-200">
                    <a href="/settings" class="flex items-center gap-2 hover:opacity-80">
                        <img src="<?= e(cup_image_url($user['cup_image'] ?? null, (int)($user['id'] ?? 0))) ?>" alt="Cup" class="w-8 h-8 rounded-lg object-cover border border-stone-200 bg-tea-50">
                        <span class="text-xs font-semibold text-slate-700 max-w-[120px] truncate hidden lg:inline"><?= e($user['full_name'] ?? '') ?></span>
                    </a>
                    <form method="post" action="/logout">
                        <?= Session::csrfField() ?>
                        <button type="submit" title="<?= e(__('logout')) ?>" class="p-2 rounded-lg text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition">
                            <i class="ti ti-logout text-base"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <!-- PWA Install Banner -->
    <div id="pwa-install-banner" class="hidden max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4 md:px-6 lg:px-8 pt-3">
        <div class="rounded-2xl bg-tea-700 text-white p-3.5 shadow-sm flex items-start justify-between gap-3">
            <div class="text-xs leading-relaxed">
                <div class="font-bold text-sm mb-0.5 flex items-center gap-1.5">
                    <i class="ti ti-device-mobile-down text-base"></i>
                    <span><?= e(__('install_app')) ?></span>
                </div>
                <div id="pwa-ios-guide" class="hidden text-tea-100"><?= e(__('install_ios_hint')) ?></div>
                <button id="pwa-install-btn" type="button" class="hidden mt-2 px-3 py-1.5 rounded-lg bg-white text-tea-800 font-bold text-xs">Install Now</button>
            </div>
            <button id="pwa-dismiss-btn" type="button" class="text-tea-200 hover:text-white text-lg leading-none px-1"><i class="ti ti-x"></i></button>
        </div>
    </div>

    <main class="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-3 sm:px-4 md:px-6 lg:px-8 pt-3.5 md:pt-6 space-y-3.5 md:space-y-6 page-enter">
        <?= $content ?>
    </main>

    <div id="tc-monkey-dock" class="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-3 sm:px-4 md:px-6 lg:px-8">
        <?php require __DIR__ . '/../partials/monkey-companion.php'; ?>
    </div>

    <!-- Mobile Bottom Navigation (Hidden on Tablet/Desktop 24" Monitors) -->
    <nav class="md:hidden fixed bottom-0 inset-x-0 bg-white/95 backdrop-blur border-t border-tea-100 shadow-[0_-2px_10px_rgba(45,106,79,0.06)] z-40 pb-[env(safe-area-inset-bottom)]">
        <?php if ($isStaffView): ?>
            <div class="max-w-lg mx-auto grid grid-cols-5 gap-1 px-1.5 h-16 items-center">
                <a href="/staff?tab=home" class="flex flex-col items-center justify-center gap-0.5 py-1.5 rounded-xl text-[11px] font-semibold <?= ($activeTab ?? '') === 'home' ? 'bg-tea-50 text-tea-700' : 'text-slate-500' ?>">
                    <i class="ti ti-home text-xl"></i>
                    <span>Home</span>
                </a>
                <a href="/staff?tab=skipped" class="flex flex-col items-center justify-center gap-0.5 py-1.5 rounded-xl text-[11px] font-semibold <?= ($activeTab ?? '') === 'skipped' ? 'bg-tea-50 text-tea-700' : 'text-slate-500' ?>">
                    <i class="ti ti-ban text-xl"></i>
                    <span>Skip</span>
                </a>
                <a href="/staff?tab=morning" class="flex flex-col items-center justify-center gap-0.5 py-1.5 rounded-xl text-[11px] font-semibold <?= in_array(($activeTab ?? ''), ['morning', 'afternoon'], true) ? 'bg-tea-50 text-tea-700' : 'text-slate-500' ?>">
                    <i class="ti ti-list-details text-xl"></i>
                    <span>Orders</span>
                </a>
                <a href="/staff?tab=urgent" class="flex flex-col items-center justify-center gap-0.5 py-1.5 rounded-xl text-[11px] font-semibold <?= ($activeTab ?? '') === 'urgent' ? 'bg-amberwarm-50 text-amberwarm-600' : 'text-slate-500' ?>">
                    <span class="relative inline-flex items-center justify-center">
                        <i class="ti ti-bolt text-xl"></i>
                        <span id="nav-urgent-badge" class="<?= $navUrgentPending > 0 ? 'inline-flex' : 'hidden' ?> absolute items-center justify-center h-4 px-1 rounded-full bg-rose-600 text-white font-bold leading-none shadow-sm" style="top:-0.5rem;right:-0.55rem;min-width:1rem;font-size:10px;"><?= $navUrgentPending > 99 ? '99+' : $navUrgentPending ?></span>
                    </span>
                    <span>Urgent</span>
                </a>
                <a href="/staff?tab=completed" class="flex flex-col items-center justify-center gap-0.5 py-1.5 rounded-xl text-[11px] font-semibold <?= ($activeTab ?? '') === 'completed' ? 'bg-emerald-50 text-emerald-700' : 'text-slate-500' ?>">
                    <i class="ti ti-circle-check text-xl"></i>
                    <span>Complete</span>
                </a>
            </div>
        <?php else: ?>
            <div class="max-w-lg mx-auto grid <?= $canGuest ? 'grid-cols-4' : 'grid-cols-3' ?> gap-1 px-2 h-16 items-center">
                <a href="/today" class="flex flex-col items-center justify-center gap-0.5 py-1.5 rounded-xl text-xs font-semibold <?= ($activeTab ?? '') === 'today' ? 'bg-tea-50 text-tea-700' : 'text-slate-500' ?>">
                    <i class="ti ti-mug text-xl"></i>
                    <span><?= e(__('today')) ?></span>
                </a>
                <a href="/history" class="flex flex-col items-center justify-center gap-0.5 py-1.5 rounded-xl text-xs font-semibold <?= ($activeTab ?? '') === 'history' ? 'bg-tea-50 text-tea-700' : 'text-slate-500' ?>">
                    <i class="ti ti-history text-xl"></i>
                    <span><?= e(__('history')) ?></span>
                </a>
                <?php if ($canGuest): ?>
                    <a href="/guest-order" class="flex flex-col items-center justify-center gap-0.5 py-1.5 rounded-xl text-xs font-semibold <?= ($activeTab ?? '') === 'more' ? 'bg-tea-50 text-tea-700' : 'text-slate-500' ?>">
                        <i class="ti ti-users-group text-xl"></i>
                        <span>Guest</span>
                    </a>
                <?php endif; ?>
                <a href="/settings" class="flex flex-col items-center justify-center gap-0.5 py-1.5 rounded-xl text-xs font-semibold <?= in_array(($activeTab ?? ''), ['settings', 'profile'], true) ? 'bg-tea-50 text-tea-700' : 'text-slate-500' ?>">
                    <i class="ti ti-settings text-xl"></i>
                    <span>Settings</span>
                </a>
            </div>
        <?php endif; ?>
    </nav>

    <?php if ($isStaffView): ?>
        <style>
        #staff-emp-search-modal {
            position: fixed;
            inset: 0;
            z-index: 62;
            background: rgba(15, 23, 42, 0.72);
            -webkit-backdrop-filter: blur(4px);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem;
        }
        #staff-emp-search-modal.hidden {
            display: none;
        }
        .staff-emp-modal-dialog {
            background: #fff;
            border: 1px solid #e7e5e4;
            border-radius: 1rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            width: 100%;
            max-width: 40rem;
            max-height: 88vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .staff-emp-modal-body {
            overflow-y: auto;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
        }
        </style>
        <div id="staff-emp-search-modal" class="hidden" role="dialog" aria-modal="true" aria-labelledby="staff-emp-modal-title">
            <div class="staff-emp-modal-dialog">
                <div class="px-4 py-3 border-b border-stone-100 flex items-center justify-between gap-2 bg-cream-50 shrink-0">
                    <div class="inline-flex items-center gap-2 min-w-0">
                        <i class="ti ti-user-search text-lg text-tea-700 shrink-0"></i>
                        <h3 id="staff-emp-modal-title" class="font-extrabold text-sm md:text-base text-tea-800 truncate">Find Employee Today</h3>
                    </div>
                    <button type="button" data-emp-modal-close="1" class="w-9 h-9 rounded-xl bg-white border border-stone-200 text-slate-600 hover:bg-stone-100 flex items-center justify-center shrink-0" aria-label="Close search">
                        <i class="ti ti-x text-lg"></i>
                    </button>
                </div>

                <div class="px-4 py-3 border-b border-stone-100 bg-white shrink-0">
                    <div class="relative flex items-center">
                        <i class="ti ti-search text-slate-400 text-base absolute left-3 pointer-events-none"></i>
                        <input
                            type="search"
                            id="staff-emp-search-input"
                            placeholder="Search by employee name or ID (e.g. Kamal, 7530)..."
                            autocomplete="off"
                            style="padding-left: 2.35rem; padding-right: 2.35rem; font-size: 16px;"
                            class="input-field w-full py-2.5 rounded-xl"
                        >
                        <button type="button" id="staff-emp-search-clear" class="hidden absolute right-2.5 w-7 h-7 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-stone-100 flex items-center justify-center" aria-label="Clear search">
                            <i class="ti ti-x text-sm"></i>
                        </button>
                    </div>
                </div>

                <div id="staff-emp-search-body" class="p-4 staff-emp-modal-body space-y-3">
                    <div class="text-center text-xs text-slate-500 py-6">Loading employees...</div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php require __DIR__ . '/../partials/cup-modal.php'; ?>
    <script src="/assets/js/app.js?v=<?= @filemtime(__DIR__ . '/../../../public/assets/js/app.js') ?: '2' ?>"></script>
    <script src="/assets/js/monkey.js?v=<?= @filemtime(__DIR__ . '/../../../public/assets/js/monkey.js') ?: '1' ?>" defer></script>
</body>
</html>
