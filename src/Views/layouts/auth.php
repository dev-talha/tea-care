<?php
$officeName = setting('office_name', 'TeaCare');
$officeLogo = setting('office_logo', '');
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
<body class="min-h-screen bg-cream-50 flex flex-col justify-between p-4">
    <main class="max-w-md w-full mx-auto my-auto py-4">
        <div class="text-center mb-6">
            <?php if ($officeLogo !== ''): ?>
                <img src="/media/logo?v=<?= e(substr(md5($officeLogo), 0, 8)) ?>" alt="<?= e($officeName) ?>" class="h-14 mx-auto mb-2 object-contain">
            <?php else: ?>
                <img src="/assets/logo.svg" alt="TeaCare" class="w-14 h-14 mx-auto mb-2 shadow-sm rounded-2xl">
            <?php endif; ?>
            <h1 class="text-2xl font-bold text-tea-800"><?= e($officeName) ?></h1>
            <p class="text-sm text-slate-500 mt-0.5"><?= e(__('tagline')) ?></p>
        </div>

        <?php require __DIR__ . '/../partials/flash.php'; ?>
        <?= $content ?>
    </main>

    <footer class="text-center text-xs text-slate-400 py-3">
        TeaCare PWA &bull; Asia/Dhaka
    </footer>
    <script src="/assets/js/app.js?v=<?= @filemtime(__DIR__ . '/../../../public/assets/js/app.js') ?: '2' ?>"></script>
</body>
</html>
