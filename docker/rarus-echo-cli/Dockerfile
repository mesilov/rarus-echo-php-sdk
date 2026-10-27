# syntax=docker/dockerfile:1
# MIT License - Rarus Echo PHP SDK

ARG PHP_EXTENSION_INSTALLER_PLATFORM=linux/amd64

FROM --platform=${PHP_EXTENSION_INSTALLER_PLATFORM} mlocati/php-extension-installer:2.4 AS php-extension-installer
FROM composer:2.8 AS composer

FROM php:8.4-cli-alpine@sha256:d8784155c4d37986be9ddd7a8950df7e78998cc48e35472cd69e2b9371424a65 AS media-builder
RUN apk add --no-cache build-base python3 xz bash nasm binutils
WORKDIR /app
COPY build/media/ /app/build/media/
COPY build/licenses/ /app/build/licenses/
COPY build/tests/linkage-check.py /app/build/tests/linkage-check.py
COPY tests/Fixtures/Media/ /app/tests/Fixtures/Media/
ARG TARGETARCH
ENV MEDIA_CC=gcc
RUN --mount=type=cache,target=/app/runtime/media-cache \
    python3 build/media/build.py "linux-${TARGETARCH}" --destination /out/media

FROM php:8.4-cli-alpine@sha256:d8784155c4d37986be9ddd7a8950df7e78998cc48e35472cd69e2b9371424a65 AS vendor

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

FROM php:8.4-cli-alpine@sha256:d8784155c4d37986be9ddd7a8950df7e78998cc48e35472cd69e2b9371424a65 AS cli-baseline

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

FROM cli-baseline AS cli
COPY --from=media-builder /out/media /opt/rarus-echo/media
