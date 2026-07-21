#!/bin/bash
set -e

cd /var/www/html

if [ ! -d vendor ]; then
  composer install --no-interaction
fi

chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rwx storage bootstrap/cache || true

exec apache2-foreground
