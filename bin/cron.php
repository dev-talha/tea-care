<?php

declare(strict_types=1);

/**
 * Single-pass CLI cron worker for cPanel / shared hosting.
 * Schedule every minute in cPanel Cron Jobs:
 *   * * * * * /usr/local/bin/php /home/YOUR_USER/tea-care/bin/cron.php >/dev/null 2>&1
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Core/helpers.php';

use App\Core\Config;
use App\Core\Database;
use App\Services\OrderService;
use App\Services\PushService;
use App\Services\ScheduleService;
use App\Services\WhatsAppService;

$baseDir = dirname(__DIR__);
Config::load($baseDir);

$logDir = $baseDir . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

$lockHandle = @fopen($logDir . '/cron.lock', 'c');
if ($lockHandle && !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    // Another cron run is still in progress
    exit(0);
}

try {
    $now = ScheduleService::now();
    $today = $now->format('Y-m-d');

    $m = OrderService::finalizeRegularSlot($today, 'morning', $now);
    $a = OrderService::finalizeRegularSlot($today, 'afternoon', $now);
    $autoCancelled = OrderService::autoCancelExpiredOrders($now);
    $pushed = PushService::processPendingJobs(25);
    $waSent = WhatsAppService::processPendingJobs(25);

    Database::execute(
        "INSERT INTO settings (setting_key, setting_value) VALUES ('scheduler_last_run_at', ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
        [$now->format('Y-m-d H:i:s')]
    );

    if ($m > 0 || $a > 0 || $autoCancelled > 0 || $pushed > 0 || $waSent > 0) {
        echo sprintf(
            "[%s] morning=%d afternoon=%d auto_cancelled=%d push=%d wa=%d\n",
            $now->format('Y-m-d H:i:s'),
            $m,
            $a,
            $autoCancelled,
            $pushed,
            $waSent
        );
    }
} catch (Throwable $e) {
    fwrite(STDERR, '[cron] Error: ' . $e->getMessage() . "\n");
    exit(1);
} finally {
    if ($lockHandle) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}
