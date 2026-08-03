#!/bin/bash
set -e

cd /var/www/html

if [ ! -d vendor ]; then
  composer install --no-interaction
fi

chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rwx storage bootstrap/cache || true

# Lab local: garante tenant Seridó (112) após volume/MySQL vazio ou wipe acidental.
if [ "${APP_ENV:-local}" = "local" ] || [ "${APP_ENV:-local}" = "development" ]; then
  php artisan db:seed --class=Database\\Seeders\\ClienteSeridoSeeder --force >/dev/null 2>&1 || true
fi

exec apache2-foreground
