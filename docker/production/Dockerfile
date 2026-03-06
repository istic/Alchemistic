FROM php:8.4-fpm-alpine

RUN docker-php-ext-install pdo_mysql opcache bcmath

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

EXPOSE 9000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
