FROM composer:2.9.8 AS composer

FROM php:8.3.36-fpm-alpine3.24

# pdo_pgsql for PostGIS, opcache for performance; build dependencies are removed afterwards.
RUN apk add --no-cache libpq \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libpq-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql \
    && docker-php-ext-enable opcache \
    && apk del .build-deps

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY fpm-pool.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY --chmod=755 entrypoint.sh /usr/local/bin/app-entrypoint

WORKDIR /app

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
