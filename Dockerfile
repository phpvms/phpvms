ARG PHP_VERSION=8.3
ARG FRANKENPHP_VERSION=latest

FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION}

ARG TZ=UTC
ARG APP_DIR=/app

# IMPORTANT: If you're using a reverse proxy use :80, else set your domain name
ENV SERVER_NAME=:80 \
    WITH_SCHEDULER=true \
    WITH_HORIZON=true \
    USER=www-data \
    ROOT=${APP_DIR}

WORKDIR ${ROOT}

# INSTALL DEPS AND PHP EXTESIONS
RUN curl -sL https://deb.nodesource.com/setup_20.x | bash -

RUN apt-get update; \
    apt-get upgrade -yqq; \
    apt-get install -yqq --no-install-recommends --show-progress \
    apt-utils \
    curl \
    wget \
    nano \
	git \
    ncdu \
    procps \
    ca-certificates \
    supervisor \
    libsodium-dev \
    unzip \
    nodejs \
    mariadb-client \
    # Install PHP extensions (included with dunglas/frankenphp)
    && install-php-extensions \
    @composer \
    pcntl \
    pdo_mysql \
    gd \
    intl \
    opcache \
    mbstring \
    bcmath \
    gmp \
    zip \
    redis \
    && apt-get -y autoremove \
    && apt-get clean \
    && docker-php-source delete \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* \
    && rm /var/log/lastlog /var/log/faillog


RUN arch="$(uname -m)" \
    && case "$arch" in \
    armhf) _cronic_fname='supercronic-linux-arm' ;; \
    aarch64) _cronic_fname='supercronic-linux-arm64' ;; \
    x86_64) _cronic_fname='supercronic-linux-amd64' ;; \
    x86) _cronic_fname='supercronic-linux-386' ;; \
    *) echo >&2 "error: unsupported architecture: $arch"; exit 1 ;; \
    esac \
    && wget -q "https://github.com/aptible/supercronic/releases/download/v0.2.29/${_cronic_fname}" \
    -O /usr/bin/supercronic \
    && chmod +x /usr/bin/supercronic \
    && mkdir -p /etc/supercronic \
    && echo "*/1 * * * * php ${ROOT}/artisan schedule:run --no-interaction" > /etc/supercronic/laravel

RUN cp ${PHP_INI_DIR}/php.ini-production ${PHP_INI_DIR}/php.ini

COPY --link --chown=${USER}:${USER} composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-autoloader \
    --no-ansi \
    --no-scripts

COPY --link . .

RUN mkdir -p \
    storage/framework/{sessions,views,cache,testing} \
    storage/logs \
    bootstrap/cache && chmod -R a+rw storage

COPY --link resources/docker/supervisord.conf /etc/supervisor/
COPY --link resources/docker/supervisord.*.conf /etc/supervisor/conf.d/

COPY --link resources/docker/php.ini ${PHP_INI_DIR}/conf.d/99-octane.ini

COPY --link resources/docker/start-task-runner /usr/local/bin/start-task-runner

RUN chmod +x /usr/local/bin/start-task-runner

# FrankenPHP embedded PHP configuration
COPY --link resources/docker/php.ini /lib/php.ini

RUN npm install --loglevel=error --no-audit
RUN npm run production

RUN cat resources/docker/utilities.sh >> ~/.bashrc
