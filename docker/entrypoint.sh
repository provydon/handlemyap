#!/usr/bin/env bash
set -e
cd /var/www/html

# Render provides $PORT; make Apache listen on it.
: "${PORT:=10000}"
sed -ri "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Database: driven by DB_URL when set (e.g. Render Postgres — persistent).
# With no DB_URL, fall back to a local ephemeral SQLite file for a demo boot.
if [ -z "${DB_URL:-}" ]; then
    mkdir -p database
    touch database/database.sqlite
fi

# First-boot app setup (all idempotent).
php artisan storage:link || true
php artisan migrate --force || true
php artisan db:seed --force || true

# Runtime writability for Apache (www-data).
chown -R www-data:www-data database storage bootstrap/cache

php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Background queue worker for AI extraction jobs. Auto-restarts if it exits so
# the queue keeps draining in this single-container deploy.
( while true; do
    php artisan queue:work --sleep=3 --tries=3 --max-time=3600 || true
    sleep 2
  done ) &

exec apache2-foreground
