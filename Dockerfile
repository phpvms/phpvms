# This Dockerfile is used to create the base phpVMS image for Docker in production.
# It is based on https://serversideup.net/open-source/docker-php/.
FROM composer:latest AS vendor

COPY composer.json composer.json
COPY composer.lock composer.lock

COPY app/Database app/Database

RUN composer install \
    --ignore-platform-reqs \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist

FROM serversideup/php:8.3-fpm

ARG WWWUSER=1000
ARG WWWGROUP=1000

# Switch to root so we can do root things
USER root

# Install missing extensions with root permissions
RUN install-php-extensions intl bcmath

# Install mariadb client (required for backups)
RUN apt-get update; \
        apt-get upgrade -yqq; \
        apt-get install -yqq --no-install-recommends --show-progress \
        mariadb-client

# Deal with permissions
RUN usermod -ou $WWWUSER www-data \
    && groupmod -og $WWWGROUP www-data

# Drop back to our unprivileged user
USER www-data

# Copy application files
COPY --chown=www-data:www-data . /var/www/html

COPY --chmod=755 ./resources/docker/run-dump-autoload.sh /etc/entrypoint.d/20-run-dump-autoload.sh

# Copy deps from the composer build stage
COPY --chown=www-data:www-data --from=vendor /app/vendor/ /var/www/html/vendor/
