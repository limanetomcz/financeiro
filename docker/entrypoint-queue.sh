#!/bin/bash
set -e

cd /var/www/html

if [ ! -d vendor ]; then
  composer install --no-interaction
fi

chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rwx storage bootstrap/cache || true

# Filas: default (orquestração) + cobranca + bancario
exec php artisan queue:work redis \
  --queue="${QUEUE_NAMES:-default,cobranca,bancario}" \
  --sleep="${QUEUE_SLEEP:-1}" \
  --tries="${QUEUE_TRIES:-3}" \
  --timeout="${QUEUE_TIMEOUT:-120}" \
  --max-time="${QUEUE_MAX_TIME:-3600}"
