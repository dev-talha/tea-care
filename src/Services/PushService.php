<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushService
{
    public static function getVapidConfig(): array
    {
        $rows = Database::fetchAll(
            "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('vapid_subject','vapid_public_key','vapid_private_key','push_enabled')"
        );
        $map = [];
        foreach ($rows as $r) {
            $map[$r['setting_key']] = (string)($r['setting_value'] ?? '');
        }

        $publicKey  = $map['vapid_public_key'] ?: (string)Config::get('VAPID_PUBLIC_KEY', '');
        $privateKey = $map['vapid_private_key'] ?: (string)Config::get('VAPID_PRIVATE_KEY', '');
        $subject    = $map['vapid_subject'] ?: (string)Config::get('VAPID_SUBJECT', 'mailto:admin@example.com');
        $enabled    = ($map['push_enabled'] ?? '1') === '1';

        return [
            'enabled'     => $enabled,
            'subject'     => $subject,
            'public_key'  => $publicKey,
            'private_key' => $privateKey,
        ];
    }

    public static function queueStaffPush(?int $orderId, string $title, string $body, string $url = '/staff', ?string $uniqueJobKey = null): void
    {
        if ($uniqueJobKey !== null) {
            $exists = Database::fetchOne('SELECT id FROM notification_jobs WHERE unique_job_key = ?', [$uniqueJobKey]);
            if ($exists) {
                return;
            }
        }

        Database::insert(
            'INSERT INTO notification_jobs (order_id, target_role, title, body, url, status, attempts, max_attempts, unique_job_key, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 0, 3, ?, NOW())',
            [$orderId, 'staff', mb_substr($title, 0, 195), mb_substr($body, 0, 495), $url, 'pending', $uniqueJobKey]
        );
    }

    public static function createStaffInAppNotification(?int $orderId, string $title, string $body, string $url = '/staff'): void
    {
        Database::insert(
            'INSERT INTO staff_inapp_notifications (order_id, title, body, url, is_read, created_at)
             VALUES (?, ?, ?, ?, 0, NOW())',
            [$orderId, mb_substr($title, 0, 195), mb_substr($body, 0, 495), $url]
        );
    }

    public static function saveSubscription(int $userId, string $endpoint, string $p256dh, string $auth, string $userAgent = ''): void
    {
        $hash = hash('sha256', $endpoint);
        $existing = Database::fetchOne('SELECT id FROM push_subscriptions WHERE endpoint_hash = ?', [$hash]);
        if ($existing) {
            Database::execute(
                'UPDATE push_subscriptions SET user_id = ?, endpoint = ?, p256dh = ?, auth = ?, user_agent = ?, is_active = 1, last_error = NULL, updated_at = NOW() WHERE id = ?',
                [$userId, $endpoint, $p256dh, $auth, mb_substr($userAgent, 0, 250), $existing['id']]
            );
        } else {
            Database::insert(
                'INSERT INTO push_subscriptions (user_id, endpoint, endpoint_hash, p256dh, auth, user_agent, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, 1, NOW())',
                [$userId, $endpoint, $hash, $p256dh, $auth, mb_substr($userAgent, 0, 250)]
            );
        }
    }

    public static function deactivateSubscription(int $userId, string $endpoint): void
    {
        $hash = hash('sha256', $endpoint);
        Database::execute(
            'UPDATE push_subscriptions SET is_active = 0, updated_at = NOW() WHERE endpoint_hash = ? AND user_id = ?',
            [$hash, $userId]
        );
    }

    public static function processPendingJobs(int $limit = 15): int
    {
        $vapid = self::getVapidConfig();
        if (!$vapid['enabled'] || $vapid['public_key'] === '' || $vapid['private_key'] === '') {
            return 0;
        }

        // Recover stale processing jobs whose claim lease (>3 minutes) expired
        Database::execute(
            "UPDATE notification_jobs
             SET status = 'pending', next_retry_at = NULL
             WHERE status = 'processing' AND processed_at IS NULL
               AND ((next_retry_at IS NOT NULL AND next_retry_at <= NOW()) OR (next_retry_at IS NULL AND created_at < DATE_SUB(NOW(), INTERVAL 3 MINUTE)))"
        );

        $jobs = Database::fetchAll(
            "SELECT * FROM notification_jobs
             WHERE status = 'pending'
               AND (next_retry_at IS NULL OR next_retry_at <= NOW())
             ORDER BY id ASC
             LIMIT " . (int)$limit
        );

        if (empty($jobs)) {
            return 0;
        }

        $processed = 0;
        foreach ($jobs as $job) {
            $claimed = Database::execute(
                "UPDATE notification_jobs
                 SET status = 'processing', attempts = attempts + 1, next_retry_at = DATE_ADD(NOW(), INTERVAL 3 MINUTE)
                 WHERE id = ? AND status = 'pending'",
                [$job['id']]
            );
            if ($claimed === 0) {
                continue;
            }
            $processed++;
            self::dispatchSingleJob($job, $vapid);
        }

        return $processed;
    }

    private static function dispatchSingleJob(array $job, array $vapid): void
    {
        $subs = Database::fetchAll(
            "SELECT ps.* FROM push_subscriptions ps
             INNER JOIN users u ON u.id = ps.user_id
             WHERE ps.is_active = 1 AND u.status = 'active' AND u.role IN ('staff', 'admin')"
        );

        if (empty($subs)) {
            Database::execute(
                "UPDATE notification_jobs SET status = 'sent', last_error = 'No active staff push subscriptions', processed_at = NOW() WHERE id = ?",
                [$job['id']]
            );
            return;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject'    => $vapid['subject'],
                    'publicKey'  => $vapid['public_key'],
                    'privateKey' => $vapid['private_key'],
                ],
            ]);

            $payload = json_encode([
                'title'   => $job['title'],
                'body'    => $job['body'],
                'url'     => $job['url'] ?: '/staff',
                'orderId' => $job['order_id'],
            ], JSON_UNESCAPED_UNICODE);

            $subMap = [];
            foreach ($subs as $s) {
                $subObj = Subscription::create([
                    'endpoint'        => $s['endpoint'],
                    'publicKey'       => $s['p256dh'],
                    'authToken'       => $s['auth'],
                    'contentEncoding' => 'aes128gcm',
                ]);
                $subMap[$s['endpoint']] = $s;
                $webPush->queueNotification($subObj, $payload);
            }

            $anySuccess = false;
            $lastErr = null;

            foreach ($webPush->flush() as $report) {
                $endpoint = $report->getEndpoint();
                $subRow = $subMap[$endpoint] ?? null;
                $subId = $subRow ? (int)$subRow['id'] : null;
                $subUserId = $subRow ? (int)$subRow['user_id'] : null;
                $statusCode = $report->getResponse()?->getStatusCode();

                if ($report->isSuccess()) {
                    $anySuccess = true;
                    if ($subId) {
                        Database::execute(
                            'UPDATE push_subscriptions SET last_success_at = NOW(), last_error = NULL WHERE id = ?',
                            [$subId]
                        );
                    }
                    Database::insert(
                        "INSERT INTO notification_logs (job_id, subscription_id, user_id, title, status, http_status, created_at)
                         VALUES (?, ?, ?, ?, 'sent', ?, NOW())",
                        [$job['id'], $subId, $subUserId, $job['title'], $statusCode]
                    );
                } elseif ($report->isSubscriptionExpired()) {
                    $lastErr ??= 'Push subscription expired';
                    if ($subId) {
                        Database::execute(
                            "UPDATE push_subscriptions SET is_active = 0, last_error = 'Subscription expired', updated_at = NOW() WHERE id = ?",
                            [$subId]
                        );
                    }
                    Database::insert(
                        "INSERT INTO notification_logs (job_id, subscription_id, user_id, title, status, http_status, error_message, created_at)
                         VALUES (?, ?, ?, ?, 'expired', ?, ?, NOW())",
                        [$job['id'], $subId, $subUserId, $job['title'], $statusCode, $report->getReason()]
                    );
                } else {
                    $lastErr = $report->getReason();
                    if ($subId) {
                        Database::execute(
                            'UPDATE push_subscriptions SET last_error = ? WHERE id = ?',
                            [$lastErr, $subId]
                        );
                    }
                    Database::insert(
                        "INSERT INTO notification_logs (job_id, subscription_id, user_id, title, status, http_status, error_message, created_at)
                         VALUES (?, ?, ?, ?, 'failed', ?, ?, NOW())",
                        [$job['id'], $subId, $subUserId, $job['title'], $statusCode, $lastErr]
                    );
                }
            }

            $attempts = (int)$job['attempts'] + 1;
            $maxAttempts = (int)$job['max_attempts'];

            if ($anySuccess || $lastErr === null) {
                Database::execute(
                    "UPDATE notification_jobs SET status = 'sent', last_error = NULL, processed_at = NOW() WHERE id = ?",
                    [$job['id']]
                );
            } elseif ($attempts < $maxAttempts) {
                $delaySec = $attempts * 60;
                Database::execute(
                    "UPDATE notification_jobs SET status = 'pending', next_retry_at = DATE_ADD(NOW(), INTERVAL ? SECOND), last_error = ? WHERE id = ?",
                    [$delaySec, $lastErr, $job['id']]
                );
            } else {
                Database::execute(
                    "UPDATE notification_jobs SET status = 'failed', last_error = ?, processed_at = NOW() WHERE id = ?",
                    [$lastErr, $job['id']]
                );
            }
        } catch (\Throwable $e) {
            $attempts = (int)$job['attempts'] + 1;
            $maxAttempts = (int)$job['max_attempts'];
            if ($attempts < $maxAttempts) {
                Database::execute(
                    "UPDATE notification_jobs SET status = 'pending', next_retry_at = DATE_ADD(NOW(), INTERVAL 60 SECOND), last_error = ? WHERE id = ?",
                    [$e->getMessage(), $job['id']]
                );
            } else {
                Database::execute(
                    "UPDATE notification_jobs SET status = 'failed', last_error = ?, processed_at = NOW() WHERE id = ?",
                    [$e->getMessage(), $job['id']]
                );
            }
        }
    }
}
