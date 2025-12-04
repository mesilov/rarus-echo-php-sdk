# syntax=docker/dockerfile:1
# MIT License - Rarus Echo PHP SDK

# Stage 1: PHP Extension Installer
FROM mlocati/php-extension-installer:2.4 AS php-extension-installer

# Stage 2: Composer
FROM composer:2.8 AS composer

# Stage 3: Development PHP environment
FROM php:8.3-cli-bookworm AS dev-php

# Build arguments for user permissions
ARG UID=1000
ARG GID=1000

# Copy tools from previous stages
COPY --from=php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Install system dependencies
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN install-php-extensions \
    bcmath \
    intl \
    pcntl \
    opcache \
    yaml \
    zip \
    curl \
    mbstring \
    xml \
    dom \
    fileinfo

# Configure PHP
COPY conf.d/php.ini /usr/local/etc/php/conf.d/99-custom.ini

# Set up working directory
WORKDIR /var/www/html

# Configure composer cache
ENV COMPOSER_CACHE_DIR=/tmp/composer/cache

# Set up www-data user with proper permissions
RUN usermod -u ${UID} www-data && \
    groupmod -g ${GID} www-data && \
    chown -R www-data:www-data /var/www/html

USER www-data

CMD ["php", "-a"]
