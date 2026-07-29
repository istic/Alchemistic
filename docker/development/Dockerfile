FROM php:8.4-fpm-alpine

LABEL maintainer="Aquarion <nicholas@istic.net>"
LABEL description="Alchemistic PHP-FPM Docker image for development"
## Note if you change this, you should mirror those changes in the production Dockerfile as well

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/bin/install-php-extensions

RUN docker-php-ext-install pdo_mysql opcache bcmath
RUN install-php-extensions opentelemetry
RUN apk add --no-cache supervisor nodejs npm shadow \
    && mkdir -p /var/log/supervisor

ARG WWWGROUP=1000
RUN groupmod -g "${WWWGROUP}" www-data

# COPY docker/production/php-opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

ARG APP_VERSION=dev
ARG APP_PR_NUMBER=
ARG APP_BRANCH=
ARG APP_ENV=local
ARG APP_NAME=Alchemistic

ENV APP_VERSION=$APP_VERSION
ENV APP_PR_NUMBER=$APP_PR_NUMBER
ENV APP_BRANCH=$APP_BRANCH
ENV APP_ENV=$APP_ENV
ENV OTEL_RESOURCE_ATTRIBUTES="service.version=$APP_VERSION,service.environment=$APP_ENV,service.name=$APP_NAME,service.revision=$APP_PR_NUMBER,service.branch=$APP_BRANCH"
ENV SUPERVISOR_PHP_COMMAND="php artisan serve --host=0.0.0.0 --port=80"
ENV SUPERVISOR_PHP_USER="www-data"

EXPOSE 80
EXPOSE 5173

ENTRYPOINT ["./docker/development/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
