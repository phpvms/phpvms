# phpVMS 8 - Build, Fly, Extend

![Build](https://github.com/phpvms/phpvms/actions/workflows/build.yml/badge.svg)![StyleCI](https://github.styleci.io/repos/93688482/shield?branch=dev)![License](https://poser.pugx.org/nabeel/phpvms/license)

phpVMS is a PHP application to run and simulate an airline. It allowed users to register, view flight schedules that you create, and file flight reports, built on the Laravel framework. The latest documentation, with installation instructions is available on the [phpVMS documentation](https://docs.phpvms.net/) site.

## Installation

A full distribution, with all the composer dependencies, is available at this [GitHub Releases](https://github.com/nabeelio/phpvms/releases) link.

### Requirements

- PHP 8.4+, extensions:
  - cURL
  - JSON
  - fileinfo
  - mbstring
  - openssl
  - pdo
  - tokenizer
  - intl
  - zip

- Database:
  - PostgreSQL 16+
  - MySQL 8.0+
  - MariaDB 10.6+
  - Redis 6.0+ (optional, recommended)

[View more details on requirements](https://docs.phpvms.net/requirements)

## Production Deployment with Docker

The reference production stack is `compose.deploy.yml`. It runs the official phpvms image (built from the repo `Dockerfile`, based on `serversideup/php:8.5-frankenphp`) with Laravel Octane worker mode enabled, plus PostgreSQL and Redis. No separate web-server sidecar is needed — FrankenPHP serves HTTP/HTTPS directly.

```shellscript
docker compose -f compose.deploy.yml up -d
```

### Octane worker mode

The image itself ships with serversideup's default classic FrankenPHP entry point (one PHP worker per request). `compose.deploy.yml` opts into Laravel Octane worker mode via a `command:` override on the `app` service — the [pattern documented upstream](https://serversideup.net/open-source/docker-php/docs/framework-guides/laravel/octane).

To fall back to classic FrankenPHP + PHP-worker mode (matches the prior PHP-FPM request semantics — slower per request but bulletproof if a worker-mode bug hits), delete the `command:` line from the `app` service and recreate the container:

```yaml
services:
  app:
    # command: [...octane:start...]   # remove this line
```

Module authors: under Octane the framework stays booted across requests, so avoid per-request data in singleton-bound services, `static` array accumulators, and `boot()`-time toggles without a matching per-request reset. See `app/Http/Middleware/DisableActivityLoggingByDefault.php` for the pattern used to keep activity logging request-scoped.

## Development Environment

The development environment uses mise. You can read about mise and install it from [here](https://mise.jdx.dev/). If you want mise to handle installing the correct PHP version, you can run:

```shellscript
mise install
```

If you install the `mise` shell script integration, the correct PHP version will automatically be selected for you.~~~~

### Running the dev server

Once dependencies are installed (`composer install` and `bun install`) and your `.env` is configured, the quickest way to get everything running is the `dev` Artisan command:

```shellscript
php artisan dev
```

This starts all the development processes you need in a single terminal, running concurrently:

- `server` — the PHP development server (`php artisan serve`), serving the app at `http://localhost:8000`
- `queue` — a queue worker (`php artisan queue:listen`) so queued jobs are processed
- `logs` — live log tailing via [Pail](https://github.com/laravel/pail) (`php artisan pail`)
- `vite` — the Vite dev server (`bun run dev`) for hot-reloading JS/CSS assets

Press `Ctrl+C` to stop all of them at once.

> The equivalent `composer dev` script is also available and does the same thing.

A full development environment can be brought up using Docker and [Laravel Sail](https://laravel.com/docs/10.x/sail), without having to install composer/npm locally

```shellscript
make docker-test

# **OR** with docker directly

docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs

# Then you can start sail
./vendor/bin/sail up
```

Then go to `http://localhost`.

Instead of repeatedly typing vendor/bin/sail to execute Sail commands, you may wish to configure a shell alias that allows you to execute Sail's commands more easily:

```shellscript
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
```

Then you can execute php, artisan, composer, npm, etc. commands using the sail prefix:

```shellscript
# PHP commands within Laravel Sail...
sail php --version

# Artisan commands within Laravel Sail...
sail artisan about

# Composer commands within Laravel Sail...
sail composer install

# Bun commands within Laravel Sail...
sail bun run dev
```

To interact with databases (MariaDB, Redis...), please refer to the Laravel Sail documentation

### Building JS/CSS assets

Yarn is required, run:

```shellscript
make build-assets
```

This will build all the assets according to the webpack file.

### Laravel Boost

If you want to use AI agents for your development workflow, please ensure you install [Laravel Boost](https://laravel.com/ai/boost) by running the following command:

```shellscript
php artisan boost:install
```

---

## Contributors

Thank you to everyone who've contributed to phpVMS!
