<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

class WhatsAppService
{
    public static function getConfig(): array
    {
        $rows = Database::fetchAll(
            "SELECT setting_key, setting_value FROM settings
             WHERE setting_key IN ('evo_enabled','evo_base_url','evo_api_key','evo_instance','evo_group_jid')"
        );
        $map = [];
        foreach ($rows as $r) {
            $map[$r['setting_key']] = (string)($r['setting_value'] ?? '');
        }

        return [
            'enabled'   => ($map['evo_enabled'] ?? '0') === '1',
            'base_url'  => rtrim(trim($map['evo_base_url'] ?? ''), '/'),
            'api_key'   => trim($map['evo_api_key'] ?? ''),
            'instance'  => trim($map['evo_instance'] ?? ''),
            'group_jid' => trim($map['evo_group_jid'] ?? ''),
        ];
    }

    public static function isConfigured(?array $cfg = null): bool
    {
        $cfg ??= self::getConfig();
        return $cfg['enabled']
            && $cfg['base_url'] !== ''
            && $cfg['api_key'] !== ''
            && $cfg['instance'] !== ''
            && $cfg['group_jid'] !== '';
    }

    public static function queueGroupMessage(?int $orderId, string $message, ?string $uniqueJobKey = null): void
    {
        if (!self::isConfigured()) {
            return;
        }

        if ($uniqueJobKey !== null) {
            $exists = Database::fetchOne('SELECT id FROM whatsapp_jobs WHERE unique_job_key = ?', [$uniqueJobKey]);
            if ($exists) {
                return;
            }
        }

        Database::insert(
            "INSERT INTO whatsapp_jobs (order_id, message, status, attempts, max_attempts, unique_job_key, created_at)
             VALUES (?, ?, 'pending', 0, 3, ?, NOW())",
            [$orderId, $message, $uniqueJobKey]
        );
    }

    public static function sendText(string $message, ?array $overrideConfig = null): array
    {
        $cfg = $overrideConfig ?? self::getConfig();
        $baseUrl = rtrim(trim((string)($cfg['base_url'] ?? '')), '/');
        $apiKey = trim((string)($cfg['api_key'] ?? ''));
        $instance = trim((string)($cfg['instance'] ?? ''));
        $groupJid = trim((string)($cfg['group_jid'] ?? ''));

        if ($baseUrl === '' || $apiKey === '' || $instance === '' || $groupJid === '') {
            return ['ok' => false, 'status' => 0, 'error' => 'Evolution API URL, API Key, Instance, and WhatsApp Group JID are all required.'];
        }

        if (!preg_match('#^https?://#i', $baseUrl)) {
            return ['ok' => false, 'status' => 0, 'error' => 'Evolution API Base URL must start with http:// or https://'];
        }

        if (!function_exists('curl_init')) {
            return ['ok' => false, 'status' => 0, 'error' => 'PHP cURL extension is not available.'];
        }

        $endpoint = $baseUrl . '/message/sendText/' . rawurlencode($instance);
        $payload = json_encode([
            'number' => $groupJid,
            'text'   => $message,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'apikey: ' . $apiKey,
            ],
        ]);

        $raw = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'status' => 0, 'error' => 'Connection error: ' . $curlErr];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['ok' => true, 'status' => $httpCode, 'error' => null];
        }

        $snippet = mb_substr(trim(strip_tags((string)$raw)), 0, 220);
        return ['ok' => false, 'status' => $httpCode, 'error' => "HTTP {$httpCode}: {$snippet}"];
    }

    public static function processPendingJobs(int $limit = 15): int
    {
        if (!self::isConfigured()) {
            return 0;
        }

        Database::execute(
            "UPDATE whatsapp_jobs
             SET status = 'pending', next_retry_at = NULL
             WHERE status = 'processing' AND processed_at IS NULL
               AND ((next_retry_at IS NOT NULL AND next_retry_at <= NOW()) OR (next_retry_at IS NULL AND created_at < DATE_SUB(NOW(), INTERVAL 3 MINUTE)))"
        );

        $jobs = Database::fetchAll(
            "SELECT * FROM whatsapp_jobs
             WHERE status = 'pending'
               AND (next_retry_at IS NULL OR next_retry_at <= NOW())
             ORDER BY id ASC
             LIMIT " . (int)$limit
        );

        if (empty($jobs)) {
            return 0;
        }

        $processed = 0;
        $cfg = self::getConfig();

        foreach ($jobs as $job) {
            $claimed = Database::execute(
                "UPDATE whatsapp_jobs
                 SET status = 'processing', attempts = attempts + 1, next_retry_at = DATE_ADD(NOW(), INTERVAL 3 MINUTE)
                 WHERE id = ? AND status = 'pending'",
                [$job['id']]
            );
            if ($claimed === 0) {
                continue;
            }

            $res = self::sendText((string)$job['message'], $cfg);
            if ($res['ok']) {
                Database::execute(
                    "UPDATE whatsapp_jobs SET status = 'sent', last_error = NULL, processed_at = NOW() WHERE id = ?",
                    [$job['id']]
                );
            } else {
                $attempts = (int)$job['attempts'] + 1;
                $maxAttempts = (int)$job['max_attempts'];
                if ($attempts < $maxAttempts) {
                    $delaySeconds = $attempts * 20;
                    Database::execute(
                        "UPDATE whatsapp_jobs SET status = 'pending', next_retry_at = DATE_ADD(NOW(), INTERVAL ? SECOND), last_error = ? WHERE id = ?",
                        [$delaySeconds, $res['error'], $job['id']]
                    );
                } else {
                    Database::execute(
                        "UPDATE whatsapp_jobs SET status = 'failed', last_error = ?, processed_at = NOW() WHERE id = ?",
                        [$res['error'], $job['id']]
                    );
                }
            }
            $processed++;
        }

        return $processed;
    }
}
