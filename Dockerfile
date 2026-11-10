# handlemyap — single-container deploy for Render (and any Docker host).
# No Node build: Tailwind, AG Grid and the PayPal JS SDK are all loaded from CDNs.
FROM php:8.3-cli-bookworm

# System libs + PHP extensions Laravel needs (SQLite, mbstring, zip, bcmath).
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libonig-dev libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite mbstring zip bcmath \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer (copied from the official image).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install PHP deps first for layer caching.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

# App source, then finish the autoloader.
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && chmod +x docker/start.sh \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/app/database/database.sqlite \
    SESSION_DRIVER=database \
    CACHE_STORE=database \
    QUEUE_CONNECTION=sync \
    HANDLEMYAP_GATEWAY=auto \
    HANDLEMYAP_EXTRACTOR=auto \
    PHP_CLI_SERVER_WORKERS=4

# Render provides $PORT; the start script migrates, seeds the demo, caches, serves.
CMD ["sh", "docker/start.sh"]
