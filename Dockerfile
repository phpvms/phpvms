# This Dockerfile is used to create the base phpVMS image for Docker in production.
# It is based on https://serversideup.net/open-source/docker-php/.
FROM composer:latest AS vendor

LABEL org.opencontainers.image.description="The official phpVMS image"

COPY composer.json composer.json
COPY composer.lock composer.lock

COPY database database

RUN composer install \
    --ignore-platform-reqs \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist

FROM serversideup/php:8.5-frankenphp

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

# Install nodejs
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
  && apt-get install -y nodejs \
  && rm -rf /var/lib/apt/lists/*

# Deal with permissions
RUN usermod -ou $WWWUSER www-data \
    && groupmod -og $WWWGROUP www-data

# Drop back to our unprivileged user
USER www-data

# Copy application files
COPY --chown=www-data:www-data . /var/www/html

# Copy deps from the composer build stage
COPY --chown=www-data:www-data --from=vendor /app/vendor/ /var/www/html/vendor/

# Build assets directly into their final location (public/build). No
# separate web container means no asset-handoff step.
RUN npm install && npm run build

COPY --chmod=755 ./resources/docker/pick-runtime-mode.sh /etc/entrypoint.d/05-pick-runtime-mode.sh
COPY --chmod=755 ./resources/docker/run-dump-autoload.sh /etc/entrypoint.d/20-run-dump-autoload.sh
