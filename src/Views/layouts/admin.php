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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2d6a4f">
    <title><?= e($title ?? __('admin_panel')) ?> — <?= e($officeName) ?></title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/png" href="/assets/icons/icon-192.png">
    <link rel="stylesheet" href="/assets/tabler/tabler-icons.min.css">
    <link rel="stylesheet" href="/assets/css/app.css?v=<?= @filemtime(__DIR__ . '/../../../public/assets/css/app.css') ?: '2' ?>">
</head>
<body class="min-h-screen text-slate-800">
    <div id="nav-progress" class="fixed top-0 left-0 h-0.5 bg-amberwarm-500 z-[100] transition-all duration-300 opacity-0 w-0 pointer-events-none"></div>
    <div class="min-h-screen flex flex-col lg:flex-row">
        <!-- Sidebar -->
        <aside class="lg:w-64 bg-tea-900 text-white shrink-0 flex flex-col">
            <div class="p-4 flex items-center justify-between border-b border-tea-800">
                <a href="/admin" class="flex items-center gap-2.5">
                    <?php if ($officeLogo !== ''): ?>
                        <img src="/media/logo?v=<?= e(substr(md5($officeLogo), 0, 8)) ?>" alt="Logo" class="h-8 w-8 rounded-lg bg-white p-0.5 object-contain">
                    <?php else: ?>
                        <img src="/assets/logo.svg" alt="TeaCare" class="h-8 w-8 rounded-xl">
                    <?php endif; ?>
                    <div>
                        <div class="font-bold text-sm leading-tight"><?= e($officeName) ?></div>
                        <div class="text-[11px] text-tea-200"><?= e(__('admin_panel')) ?></div>
                    </div>
                </a>
                <button type="button" onclick="document.getElementById('admin-nav').classList.toggle('hidden')" class="lg:hidden px-2.5 py-1.5 rounded-lg bg-tea-800 text-base">
                    <i class="ti ti-menu-2"></i>
                </button>
            </div>

            <nav id="admin-nav" class="hidden lg:block p-3 space-y-1 flex-1">
                <?php foreach ($navItems as $item): ?>
                    <?php $isActive = ($activeNav ?? '') === $item['key']; ?>
                    <a href="<?= e($item['href']) ?>" class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= $isActive ? 'bg-tea-600 text-white shadow-sm' : 'text-tea-100 hover:bg-tea-800' ?>">
                        <i class="ti <?= e($item['icon']) ?> text-lg"></i>
                        <span><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>

                <div class="pt-4 mt-4 border-t border-tea-800 space-y-1">
                    <a href="/staff" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-sm text-amberwarm-100 hover:bg-tea-800">
                        <i class="ti ti-list-check text-lg"></i>
                        <span>Staff Mobile View</span>
                    </a>
                    <a href="/today" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-sm text-tea-200 hover:bg-tea-800">
                        <i class="ti ti-mug text-lg"></i>
                        <span>Employee View</span>
                    </a>
                </div>
            </nav>

            <div class="hidden lg:flex p-3 border-t border-tea-800 items-center justify-between text-xs">
                <div class="truncate">
                    <div class="font-semibold truncate"><?= e($user['full_name'] ?? '') ?></div>
                    <div class="text-tea-300"><?= e($user['employee_id'] ?? '') ?></div>
                </div>
                <form method="post" action="/logout">
                    <?= Session::csrfField() ?>
                    <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-tea-800 hover:bg-tea-700 text-tea-100 inline-flex items-center gap-1">
                        <i class="ti ti-logout"></i><span><?= e(__('logout')) ?></span>
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 min-w-0 flex flex-col">
            <header class="bg-white border-b border-stone-200 px-4 lg:px-8 h-14 flex items-center justify-between">
                <h1 class="text-lg font-bold text-slate-800 truncate"><?= e($title ?? __('dashboard')) ?></h1>
                <div class="flex items-center gap-3">
                    <form method="post" action="/logout" class="lg:hidden">
                        <?= Session::csrfField() ?>
                        <button type="submit" class="text-xs font-semibold text-rose-600 inline-flex items-center gap-1">
                            <i class="ti ti-logout"></i><span><?= e(__('logout')) ?></span>
                        </button>
                    </form>
                </div>
            </header>

            <main class="p-4 lg:p-8 max-w-7xl w-full mx-auto space-y-6 page-enter">
                <?php require __DIR__ . '/../partials/flash.php'; ?>
                <?= $content ?>
            </main>
        </div>
    </div>
    <?php require __DIR__ . '/../partials/cup-modal.php'; ?>
    <script src="/assets/js/app.js?v=<?= @filemtime(__DIR__ . '/../../../public/assets/js/app.js') ?: '2' ?>"></script>
</body>
</html>
