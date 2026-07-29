#!/bin/sh
set -e

echo "[entrypoint] Starting entrypoint script..."

if [ -n "${WWWUSER}" ] && [ "${WWWUSER}" != "$(id -u www-data)" ]; then
    echo "[entrypoint] Remapping www-data user to WWWUSER=${WWWUSER}..."
    usermod -u "${WWWUSER}" www-data
fi

if [ ! -f .env ]; then
    echo "[entrypoint] ERROR: .env file is missing. Please create it from .env.example." >&2
    exit 1
fi
. .env

if [ -z "${APP_KEY}" ]; then
    echo "[entrypoint] ERROR: APP_KEY is not set. Set it in your environment." >&2
    exit 1
fi

echo "[entrypoint] Creating storage directories..."
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/public


if [ ! -f storage/oauth-private.key ] || [ ! -f storage/oauth-public.key ]; then
    echo "[entrypoint] Generating Passport encryption keys..."
    php artisan passport:keys --force || {
        echo "[entrypoint] ERROR: php artisan passport:keys failed" >&2
        exit 1
    }
fi

php artisan config:cache || {
    echo "[entrypoint] ERROR: php artisan config:cache failed" >&2
    exit 1
}
php artisan route:cache || {
    echo "[entrypoint] ERROR: php artisan route:cache failed" >&2
    exit 1
}
php artisan view:cache || {
    echo "[entrypoint] ERROR: php artisan view:cache failed" >&2
    exit 1
}

exec "$@"
