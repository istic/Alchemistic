#!/bin/sh
set -e

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
