#!/bin/sh
set -e

mkdir -p /var/www/html/storage/uploads/cups /var/www/html/storage/uploads/logo /var/www/html/storage/logs
chown -R www-data:www-data /var/www/html/storage

echo "[TeaCare] Running database migrations and seed check..."
php /var/www/html/bin/migrate.php

echo "[TeaCare] Checking VAPID keys..."
php /var/www/html/bin/generate-vapid.php

echo "[TeaCare] Starting PHP-FPM..."
exec "$@"
