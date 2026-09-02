# syntax=docker/dockerfile:1
# MIT License - Rarus Echo PHP SDK

ARG PHP_EXTENSION_INSTALLER_PLATFORM=linux/amd64

FROM --platform=${PHP_EXTENSION_INSTALLER_PLATFORM} mlocati/php-extension-installer:2.4 AS php-extension-installer
FROM composer:2.8 AS composer

FROM php:8.4-cli-alpine AS vendor

COPY --from=php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN apk add --no-cache \
    git \
    unzip

RUN set -eux; \
    php -m | grep -q '^curl$' || install-php-extensions curl; \
    php -m | grep -q '^fileinfo$' || install-php-extensions fileinfo; \
    php -m | grep -q '^mbstring$' || install-php-extensions mbstring

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_CACHE_DIR=/tmp/composer/cache

COPY composer.json ./
COPY docker/rarus-echo-cli/composer.lock ./composer.lock

RUN --mount=type=cache,target=/tmp/composer/cache \
    composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts

COPY bin ./bin
COPY src ./src

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && chmod +x bin/rarus-echo

RUN php -r 'require "/app/vendor/autoload.php"; \Http\Discovery\Psr17FactoryDiscovery::findRequestFactory(); \Http\Discovery\Psr17FactoryDiscovery::findResponseFactory(); \Http\Discovery\Psr17FactoryDiscovery::findStreamFactory(); \Http\Discovery\Psr18ClientDiscovery::find();'

FROM php:8.4-cli-alpine AS cli

COPY --from=php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN apk add --no-cache \
    ca-certificates

RUN set -eux; \
    php -m | grep -q '^curl$' || install-php-extensions curl; \
    php -m | grep -q '^fileinfo$' || install-php-extensions fileinfo; \
    php -m | grep -q '^mbstring$' || install-php-extensions mbstring

WORKDIR /app

COPY docker/rarus-echo-cli/conf.d/php.ini /usr/local/etc/php/conf.d/rarus-echo-cli.ini
COPY --from=vendor /app /app

RUN ln -s /app/bin/rarus-echo /usr/local/bin/rarus-echo

ENTRYPOINT ["rarus-echo"]
CMD ["list"]
