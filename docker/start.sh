#!/usr/bin/env sh
set -e
cd /app

# SQLite lives on the container's ephemeral disk; (re)create it on each boot.
mkdir -p database
touch database/database.sqlite

# Use the public URL Render assigns, so generated links / PayPal return URLs are correct.
if [ -n "$RENDER_EXTERNAL_URL" ]; then
    export APP_URL="$RENDER_EXTERNAL_URL"
fi

php artisan config:clear

# Schema + demo data (both idempotent).
php artisan migrate --force
php artisan db:seed --force
php artisan db:seed --class="Database\\Seeders\\DemoSeeder" --force

# Bake config + views for speed. (No route:cache — some routes use closures.)
php artisan config:cache
php artisan view:cache

exec php artisan serve --host 0.0.0.0 --port "${PORT:-8000}"
