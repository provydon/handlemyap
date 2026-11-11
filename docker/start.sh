#!/usr/bin/env sh
set -e
cd /app

# SQLite database path. In production DB_DATABASE points at a persistent Render
# disk (e.g. /data/database.sqlite); for a plain `docker run` it falls back to
# the image's ephemeral path. Create the directory + file if missing.
DB_PATH="${DB_DATABASE:-/app/database/database.sqlite}"
DB_DIR="$(dirname "$DB_PATH")"
mkdir -p "$DB_DIR"
[ -f "$DB_PATH" ] || touch "$DB_PATH"

# Uploaded invoice files. On the persistent disk when FILESYSTEM_LOCAL_ROOT is set.
if [ -n "$FILESYSTEM_LOCAL_ROOT" ]; then
    mkdir -p "$FILESYSTEM_LOCAL_ROOT"
fi

# Canonical URL for generated links / PayPal return URLs. Prefer an explicitly
# configured APP_URL (e.g. the custom domain); fall back to the URL Render assigns.
if [ -z "$APP_URL" ] && [ -n "$RENDER_EXTERNAL_URL" ]; then
    export APP_URL="$RENDER_EXTERNAL_URL"
fi

php artisan config:clear

# Schema is always brought up to date (migrations are safe to re-run).
php artisan migrate --force

# Seed demo data ONLY on a fresh database. The sentinel lives next to the DB —
# on the persistent disk in production — so once the data exists, reboots and
# redeploys leave submitted invoices and payment state untouched. (On an
# ephemeral `docker run` the sentinel is ephemeral too, so local always seeds.)
if [ ! -f "$DB_DIR/.seeded" ]; then
    echo "Fresh database — seeding demo data."
    php artisan db:seed --force
    php artisan db:seed --class="Database\\Seeders\\DemoSeeder" --force
    touch "$DB_DIR/.seeded"
else
    echo "Existing database — skipping seed (data persisted)."
fi

# Bake config + views for speed. (No route:cache — some routes use closures.)
php artisan config:cache
php artisan view:cache

exec php artisan serve --host 0.0.0.0 --port "${PORT:-8000}"
