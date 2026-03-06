# Stage 1: Build frontend assets
FROM node:22-alpine AS node-builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# Stage 2: Install PHP dependencies
FROM composer:2 AS composer-builder
WORKDIR /app
COPY composer.json composer.lock ./
RUN --mount=type=secret,id=flux_username \
    --mount=type=secret,id=flux_license_key \
    composer config http-basic.composer.fluxui.dev \
        "$(cat /run/secrets/flux_username)" \
        "$(cat /run/secrets/flux_license_key)"
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts
COPY . .

# Stage 3: Production image
FROM php:8.4-fpm-alpine

RUN docker-php-ext-install pdo_mysql opcache bcmath

COPY docker/production/php-opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/production/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www/html

COPY --chown=www-data:www-data --from=composer-builder /app .
COPY --chown=www-data:www-data --from=node-builder /app/public/build ./public/build

RUN mkdir -p storage/logs \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 9000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
