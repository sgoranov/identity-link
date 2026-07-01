# syntax=docker/dockerfile:1
FROM php:8.5-cli-bookworm AS test

RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    unzip

RUN docker-php-ext-install zip pdo_pgsql
RUN pecl install xdebug redis && docker-php-ext-enable xdebug redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .
RUN composer install --no-interaction --no-scripts --no-progress

CMD ["vendor/bin/phpunit"]


FROM dunglas/frankenphp:1-php8.5-bookworm AS base

RUN install-php-extensions \
    pgsql \
    pdo_pgsql \
    redis \
    zip \
    intl

ENV SERVER_NAME=":9000"
ENV CADDY_AUTO_HTTPS=off

WORKDIR /app

ENTRYPOINT ["/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

FROM base AS dev

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Dev target: sources are expected to be mounted via volume at /app.
COPY ./docker/entrypoint-dev.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

RUN install-php-extensions xdebug

FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-scripts \
    --no-autoloader

COPY . .

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

FROM base AS prod

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY ./docker/entrypoint-prod.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh