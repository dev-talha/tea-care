<?php
use App\Core\Session;

$officeName = setting('office_name', 'TeaCare');
$officeLogo = setting('office_logo', '');
$navItems = [
    ['key' => 'dashboard',     'href' => '/admin',               'icon' => 'ti-layout-dashboard', 'label' => __('dashboard')],
    ['key' => 'employees',     'href' => '/admin/employees',     'icon' => 'ti-users',            'label' => __('employees')],
    ['key' => 'departments',   'href' => '/admin/departments',   'icon' => 'ti-building',         'label' => __('departments')],
    ['key' => 'rooms',         'href' => '/admin/rooms',         'icon' => 'ti-door',             'label' => __('rooms')],
    ['key' => 'orders',        'href' => '/admin/orders',        'icon' => 'ti-receipt',          'label' => __('orders')],
    ['key' => 'reports',       'href' => '/admin/reports',       'icon' => 'ti-chart-bar',        'label' => __('reports')],
    ['key' => 'schedule',      'href' => '/admin/schedule',      'icon' => 'ti-calendar-time',    'label' => __('schedule_holidays')],
    ['key' => 'notifications', 'href' => '/admin/notifications', 'icon' => 'ti-bell',             'label' => __('notifications')],
    ['key' => 'settings',      'href' => '/admin/settings',      'icon' => 'ti-settings',         'label' => __('settings')],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#2d6a4f">
    <title><?= e($title ?? __('admin_panel')) ?> — <?= e($officeName) ?></title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/png" href="/assets/icons/icon-192.png">
    <link rel="stylesheet" href="/assets/tabler/tabler-icons.min.css">
    <link rel="stylesheet" href="/assets/css/app.css?v=<?= @filemtime(__DIR__ . '/../../../public/assets/css/app.css') ?: '2' ?>">
    <style>
        #admin-nav {
            flex: 1 1 auto;
            overflow-y: auto;
            overscroll-behavior: contain;
        }
        #admin-sidebar-footer {
            flex-shrink: 0;
        }
        #admin-page-header {
            position: sticky;
            top: 0;
            z-index: 30;
        }
        @media (min-width: 1024px) {
            #admin-sidebar {
                position: fixed;
                top: 0;
                bottom: 0;
                left: 0;
                width: 16rem;
                height: 100vh;
                z-index: 40;
                display: flex;
                flex-direction: column;
            }
            #admin-main-wrap {
                margin-left: 16rem;
                min-height: 100vh;
            }
            #admin-backdrop,
            #admin-drawer-close,
            #admin-mobile-menu-btn {
                display: none !important;
            }
        }
        @media (max-width: 1023px) {
            #admin-sidebar {
                position: fixed;
                top: 0;
                bottom: 0;
                left: 0;
                width: 17.5rem;
                max-width: 85vw;
                height: 100dvh;
                z-index: 50;
                display: flex;
                flex-direction: column;
                transform: translateX(-105%);
                transition: transform 220ms ease;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.28);
            }
            #admin-sidebar.is-open {
                transform: translateX(0);
            }
            #admin-backdrop {
                position: fixed;
                inset: 0;
                background: rgba(15, 23, 42, 0.55);
                backdrop-filter: blur(2px);
                z-index: 45;
                display: none;
            }
            #admin-backdrop.is-open {
                display: block;
            }
        }
    </style>
</head>
<body class="min-h-screen text-slate-800">
    <div id="nav-progress" class="fixed top-0 left-0 h-0.5 bg-amberwarm-500 z-[100] transition-all duration-300 opacity-0 w-0 pointer-events-none"></div>
    <div id="admin-backdrop" onclick="toggleAdminDrawer(false)" aria-hidden="true"></div>

    <div class="min-h-screen">
        <!-- Fixed Desktop Sidebar / Slide-In Mobile Drawer -->
        <aside id="admin-sidebar" class="bg-tea-900 text-white">
            <div class="p-4 flex items-center justify-between border-b border-tea-800 shrink-0">
                <a href="/admin" class="flex items-center gap-2.5 min-w-0">
                    <?php if ($officeLogo !== ''): ?>
                        <img src="/media/logo?v=<?= e(substr(md5($officeLogo), 0, 8)) ?>" alt="Logo" class="h-8 w-8 rounded-lg bg-white p-0.5 object-contain shrink-0">
                    <?php else: ?>
                        <img src="/assets/logo.svg" alt="TeaCare" class="h-8 w-8 rounded-xl shrink-0">
                    <?php endif; ?>
                    <div class="min-w-0">
                        <div class="font-bold text-sm leading-tight truncate"><?= e($officeName) ?></div>
                        <div class="text-[11px] text-tea-200"><?= e(__('admin_panel')) ?></div>
                    </div>
                </a>
                <button id="admin-drawer-close" type="button" onclick="toggleAdminDrawer(false)" aria-label="Close menu" class="px-2.5 py-1.5 rounded-lg bg-tea-800 hover:bg-tea-700 text-base text-white">
                    <i class="ti ti-x"></i>
                </button>
            </div>

            <nav id="admin-nav" class="p-3 space-y-1">
                <?php foreach ($navItems as $item): ?>
                    <?php $isActive = ($activeNav ?? '') === $item['key']; ?>
                    <a href="<?= e($item['href']) ?>" class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= $isActive ? 'bg-tea-600 text-white shadow-sm' : 'text-tea-100 hover:bg-tea-800' ?>">
                        <i class="ti <?= e($item['icon']) ?> text-lg shrink-0"></i>
                        <span class="truncate"><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>

                <div class="pt-4 mt-4 border-t border-tea-800 space-y-1">
                    <a href="/staff" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-sm text-amberwarm-100 hover:bg-tea-800">
                        <i class="ti ti-list-check text-lg shrink-0"></i>
                        <span>Staff Mobile View</span>
                    </a>
                    <a href="/today" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-sm text-tea-200 hover:bg-tea-800">
                        <i class="ti ti-mug text-lg shrink-0"></i>
                        <span>Employee View</span>
                    </a>
                </div>
            </nav>

            <div id="admin-sidebar-footer" class="p-3 border-t border-tea-800 bg-tea-900 flex items-center justify-between gap-2 text-xs">
                <div class="min-w-0 flex-1">
                    <div class="font-semibold truncate text-white"><?= e($user['full_name'] ?? '') ?></div>
                    <div class="text-tea-300 truncate"><?= e($user['employee_id'] ?? '') ?></div>
                </div>
                <form method="post" action="/logout" class="shrink-0">
                    <?= Session::csrfField() ?>
                    <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-semibold inline-flex items-center gap-1 transition">
                        <i class="ti ti-logout"></i><span><?= e(__('logout')) ?></span>
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <div id="admin-main-wrap" class="min-w-0 flex flex-col">
            <header id="admin-page-header" class="bg-white/95 backdrop-blur border-b border-stone-200 px-3 sm:px-4 lg:px-8 h-14 flex items-center justify-between gap-2">
                <div class="flex items-center gap-2.5 min-w-0">
                    <button id="admin-mobile-menu-btn" type="button" onclick="toggleAdminDrawer(true)" aria-label="Open admin menu" class="w-9 h-9 rounded-xl bg-tea-50 border border-tea-200 text-tea-800 flex items-center justify-center shrink-0">
                        <i class="ti ti-menu-2 text-lg"></i>
                    </button>
                    <h1 class="text-base sm:text-lg font-bold text-slate-800 truncate"><?= e($title ?? __('dashboard')) ?></h1>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <form method="post" action="/logout">
                        <?= Session::csrfField() ?>
                        <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-rose-50 border border-rose-200 hover:bg-rose-100 text-xs font-bold text-rose-700 inline-flex items-center gap-1 transition">
                            <i class="ti ti-logout"></i><span><?= e(__('logout')) ?></span>
                        </button>
                    </form>
                </div>
            </header>

            <main class="p-3 sm:p-4 lg:p-8 max-w-7xl w-full mx-auto space-y-5 sm:space-y-6 page-enter">
                <?= $content ?>
            </main>
            <?php require __DIR__ . '/../partials/flash.php'; ?>
        </div>
    </div>
    <?php require __DIR__ . '/../partials/cup-modal.php'; ?>
    <script>
        function toggleAdminDrawer(open) {
            const sb = document.getElementById('admin-sidebar');
            const bd = document.getElementById('admin-backdrop');
            if (!sb || !bd) return;
            const next = typeof open === 'boolean' ? open : !sb.classList.contains('is-open');
            sb.classList.toggle('is-open', next);
            bd.classList.toggle('is-open', next);
            document.body.style.overflow = next ? 'hidden' : '';
        }
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const sb = document.getElementById('admin-sidebar');
                if (sb && sb.classList.contains('is-open')) {
                    toggleAdminDrawer(false);
                }
            }
        });
    </script>
    <script src="/assets/js/app.js?v=<?= @filemtime(__DIR__ . '/../../../public/assets/js/app.js') ?: '2' ?>"></script>
</body>
</html>
