FROM php:8.2-fpm-alpine AS base

RUN apk add --no-cache \
        nginx \
        postgresql16-client \
        libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apk del libpq-dev

COPY docker/php/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/php/php.ini    /usr/local/etc/php/conf.d/app.ini
COPY docker/php/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .

RUN mkdir -p /var/log/nginx /var/run && \
    chown -R www-data:www-data /var/log/nginx /var/run

EXPOSE 80

HEALTHCHECK --interval=15s --timeout=5s --start-period=10s --retries=3 \
    CMD wget -qO- http://localhost/index.php || exit 1

ENTRYPOINT ["/entrypoint.sh"]
