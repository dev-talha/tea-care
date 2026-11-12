<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Core/helpers.php';

use App\Core\Config;
use App\Core\Database;
use App\Services\OrderService;
use App\Services\PushService;
use App\Services\ScheduleService;
use App\Services\WhatsAppService;

Config::load(dirname(__DIR__));

echo "[scheduler] TeaCare scheduler started (timezone: " . date_default_timezone_get() . ").\n";

while (true) {
    try {
        Database::reset();
        $now = ScheduleService::now();
        $today = $now->format('Y-m-d');

        // Finalize regular sessions if cutoff passed (idempotent via regular_unique_key)
        $m = OrderService::finalizeRegularSlot($today, 'morning', $now);
        $a = OrderService::finalizeRegularSlot($today, 'afternoon', $now);
        if ($m > 0 || $a > 0) {
            echo "[" . $now->format('Y-m-d H:i:s') . "] Finalized orders: morning={$m}, afternoon={$a}\n";
        }

        // System auto-cancel expired orders (>3h pending unaccepted, or >3h accepted/preparing undelivered)
        $autoCancelled = OrderService::autoCancelExpiredOrders($now);
        if ($autoCancelled > 0) {
            echo "[" . $now->format('Y-m-d H:i:s') . "] System-cancelled expired orders: {$autoCancelled}\n";
        }

        // Process queued web push notifications
        $pushed = PushService::processPendingJobs(20);
        if ($pushed > 0) {
            echo "[" . $now->format('Y-m-d H:i:s') . "] Processed push jobs: {$pushed}\n";
        }

        // Process queued WhatsApp Evolution API messages
        $waSent = WhatsAppService::processPendingJobs(20);
        if ($waSent > 0) {
            echo "[" . $now->format('Y-m-d H:i:s') . "] Processed WhatsApp jobs: {$waSent}\n";
        }

        // Record heartbeat
        Database::execute(
            "INSERT INTO settings (setting_key, setting_value) VALUES ('scheduler_last_run_at', ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
            [$now->format('Y-m-d H:i:s')]
        );
    } catch (Throwable $e) {
        fwrite(STDERR, "[scheduler] Error: " . $e->getMessage() . "\n");
    }

    sleep(5);
}
