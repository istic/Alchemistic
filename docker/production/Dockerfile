FROM php:8.4-fpm-alpine

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/bin/install-php-extensions

RUN docker-php-ext-install pdo_mysql opcache bcmath \
    && install-php-extensions opentelemetry

COPY docker/production/php-opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/production/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .

RUN mkdir -p storage/logs \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

ARG APP_VERSION=dev
ARG APP_PR_NUMBER=
ARG APP_BRANCH=
ARG APP_ENV=production
ARG APP_NAME=Alchemistic

ENV APP_VERSION=$APP_VERSION
ENV APP_PR_NUMBER=$APP_PR_NUMBER
ENV APP_BRANCH=$APP_BRANCH

ENV OTEL_RESOURCE_ATTRIBUTES="service.version=$APP_VERSION,service.environment=$APP_ENV,service.name=$APP_NAME,service.revision=$APP_PR_NUMBER,service.branch=$APP_BRANCH"

EXPOSE 9000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
