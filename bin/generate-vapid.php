<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Core/helpers.php';

use App\Core\Config;
use App\Core\Database;
use Minishlink\WebPush\VAPID;

Config::load(dirname(__DIR__));

$existing = Database::fetchOne(
    "SELECT setting_value FROM settings WHERE setting_key = 'vapid_public_key'"
);

if ($existing && !empty($existing['setting_value'])) {
    echo "[vapid] VAPID keys already configured.\n";
    exit(0);
}

$envPub = Config::get('VAPID_PUBLIC_KEY', '');
$envPriv = Config::get('VAPID_PRIVATE_KEY', '');

if ($envPub !== '' && $envPriv !== '') {
    $keys = ['publicKey' => $envPub, 'privateKey' => $envPriv];
} else {
    $keys = VAPID::createVapidKeys();
}

$subject = Config::get('VAPID_SUBJECT', 'mailto:admin@example.com');

$stmt = Database::pdo()->prepare(
    'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
);
$stmt->execute(['vapid_subject', $subject]);
$stmt->execute(['vapid_public_key', $keys['publicKey']]);
$stmt->execute(['vapid_private_key', $keys['privateKey']]);

echo "[vapid] VAPID keys generated and stored.\n";
