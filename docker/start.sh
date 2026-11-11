#!/usr/bin/env sh
set -e
cd /app

# The production database is a remote PostgreSQL (dashboard data persists there);
# uploaded invoice files go to S3. For a plain `docker run` the app falls back to
# a local SQLite file, so create it when (and only when) SQLite is the driver.
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    DB_PATH="${DB_DATABASE:-/app/database/database.sqlite}"
    mkdir -p "$(dirname "$DB_PATH")"
    [ -f "$DB_PATH" ] || touch "$DB_PATH"
fi

# Canonical URL for generated links / PayPal return URLs. Prefer an explicitly
# configured APP_URL (e.g. the custom domain); fall back to the URL Render assigns.
if [ -z "$APP_URL" ] && [ -n "$RENDER_EXTERNAL_URL" ]; then
    export APP_URL="$RENDER_EXTERNAL_URL"
fi

php artisan config:clear

# Bring the schema up to date (safe to re-run) and remember whether anything ran.
# Seed demo data ONLY on a fresh database: if migrations just created the tables
# we seed once; on every later boot the persisted data is left untouched. This is
# driver-agnostic, so it works the same on Postgres and SQLite.
MIGRATE_OUT="$(php artisan migrate --force 2>&1)"
echo "$MIGRATE_OUT"
if echo "$MIGRATE_OUT" | grep -qi "Nothing to migrate"; then
    echo "Existing database — skipping seed (data persisted)."
else
    echo "Fresh database — seeding demo data."
    php artisan db:seed --force
    php artisan db:seed --class="Database\\Seeders\\DemoSeeder" --force
fi

# Bake config + views for speed. (No route:cache — some routes use closures.)
php artisan config:cache
php artisan view:cache

exec php artisan serve --host 0.0.0.0 --port "${PORT:-8000}"
