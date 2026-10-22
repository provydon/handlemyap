#!/usr/bin/env bash
set -e
cd /var/www/html

# Render provides $PORT; make Apache listen on it.
: "${PORT:=10000}"
sed -ri "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# SQLite store for the demo (ephemeral — reseeded on each boot). Swap to the
# Postgres blueprint (render.yaml) for persistence.
mkdir -p database
touch database/database.sqlite

# First-boot app setup (all idempotent).
php artisan storage:link || true
php artisan migrate --force || true
php artisan db:seed --force || true

# Runtime writability for Apache (www-data).
chown -R www-data:www-data database storage bootstrap/cache

php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

exec apache2-foreground
