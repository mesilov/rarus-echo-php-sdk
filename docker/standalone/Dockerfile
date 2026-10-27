# syntax=docker/dockerfile:1
# Adapted from bitrix24-cli; see build/THIRD-PARTY-NOTICES.md.
FROM composer:2.10.3@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac AS composer
FROM php:8.5.11-cli-bookworm@sha256:819080ed413631f6ec564184dd9acd08d9425bd863b9f8b5876b5ea2147d55e7 AS base
COPY --from=composer /usr/bin/composer /usr/bin/composer
RUN apt-get update && apt-get install -y --no-install-recommends \
    python3 autoconf automake bison build-essential ca-certificates cmake curl \
    file flex git gzip libtool musl-tools patch pkgconf re2c tar unzip xz-utils \
    libicu-dev autopoint \
    && docker-php-ext-install bcmath intl pcntl \
    && rm -rf /var/lib/apt/lists/*
ENV PATH="/usr/local/musl/bin:${PATH}" COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app

FROM base AS runtime-builder
ARG TARGETARCH
COPY build/config.json build/runtime.py /app/build/
COPY build/patches/ /app/build/patches/
RUN --mount=type=secret,id=github_token \
    GITHUB_TOKEN="$(cat /run/secrets/github_token 2>/dev/null || true)" \
    python3 build/runtime.py "linux-${TARGETARCH}"

FROM base AS source
COPY . /app
ARG ECHO_SOURCE_SHA
ARG ECHO_RELEASE_VERSION
ENV ECHO_SOURCE_SHA=${ECHO_SOURCE_SHA} ECHO_RELEASE_VERSION=${ECHO_RELEASE_VERSION}

FROM source AS phar-builder
RUN python3 build/package.py phar
FROM scratch AS phar-artifacts
COPY --from=phar-builder /app/dist/phar/ /

FROM source AS linux-builder
ARG TARGETARCH
COPY --from=runtime-builder /app/runtime/ /app/runtime/
RUN --mount=type=secret,id=github_token \
    GITHUB_TOKEN="$(cat /run/secrets/github_token 2>/dev/null || true)" \
    python3 build/package.py "linux-${TARGETARCH}"
FROM scratch AS linux-artifacts
COPY --from=linux-builder /app/dist/assets/ /
