<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\PushService;

class PushController
{
    public function vapidKey(): void
    {
        Auth::requireAuth();
        $cfg = PushService::getVapidConfig();
        Response::json([
            'ok'        => true,
            'enabled'   => $cfg['enabled'],
            'publicKey' => $cfg['public_key'],
        ]);
    }

    public function subscribe(): void
    {
        $user = Auth::requireRole(['staff', 'admin']);
        $body = Request::jsonBody();
        $sub = $body['subscription'] ?? $body;
        $endpoint = trim((string)($sub['endpoint'] ?? ''));
        $p256dh = trim((string)($sub['keys']['p256dh'] ?? ''));
        $auth = trim((string)($sub['keys']['auth'] ?? ''));

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            Response::json(['ok' => false, 'error' => 'Invalid push subscription payload.'], 422);
        }

        PushService::saveSubscription(
            (int)$user['id'],
            $endpoint,
            $p256dh,
            $auth,
            (string)($_SERVER['HTTP_USER_AGENT'] ?? '')
        );

        Response::json(['ok' => true, 'message' => 'Push notifications enabled on this device.']);
    }

    public function unsubscribe(): void
    {
        $user = Auth::requireRole(['staff', 'admin']);
        $body = Request::jsonBody();
        $endpoint = trim((string)($body['endpoint'] ?? ''));
        if ($endpoint !== '') {
            PushService::deactivateSubscription((int)$user['id'], $endpoint);
        }
        Response::json(['ok' => true]);
    }

    public function test(): void
    {
        $user = Auth::requireRole(['staff', 'admin']);
        PushService::queueStaffPush(
            null,
            'TeaCare Test Notification',
            'Push notification is working for ' . $user['full_name'] . '!',
            '/staff'
        );
        // Process immediately so user gets instant test feedback
        PushService::processPendingJobs(5);
        Response::json(['ok' => true, 'message' => 'Test push sent!']);
    }
}
