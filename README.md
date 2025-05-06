# phpVMS <sup>7</sup>

[![Build](https://github.com/phpvms/phpvms/actions/workflows/build.yml/badge.svg)](https://github.com/phpvms/phpvms/actions/workflows/build.yml)  ![StyleCI](https://github.styleci.io/repos/93688482/shield?branch=dev) [![License](https://poser.pugx.org/nabeel/phpvms/license)](https://packagist.org/packages/nabeel/phpvms)

phpVMS is a PHP based application to run and simulate and airline. It allowed users to register, view flight schedules that you create, and file flight reports, built on the Laravel framework. The latest documentation, with installation instructions is available [on the phpVMS documentation](https://docs.phpvms.net/) page.

## Installation

A full distribution, with all of the composer dependencies, is available at this 
[GitHub Releases](https://github.com/nabeelio/phpvms/releases) link. 

### Requirements

- PHP 8.2+, extensions:
  - cURL
  - JSON
  - fileinfo
  - mbstring
  - openssl
  - pdo
  - tokenizer
  - bcmath
  - intl
  - zip
  - pdo_sqlite
  - sqlite3
- Database:
  - MySQL 5.7+ (or MySQL variant, including MariaDB and Percona)

[View more details on requirements](https://docs.phpvms.net/requirements)

### Installer

1. Upload to your server
1. Visit the site, and follow the link to the installer

[View installation details](https://docs.phpvms.net/installation)

## Development Environment with Docker

A full development environment can be brought up using Docker and [Laravel Sail](https://laravel.com/docs/10.x/sail), without having to install composer/npm locally

```bash
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
```bash
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
```

Then you can execute php, artisan, composer, npm, etc. commands using the sail prefix:
```bash
# PHP commands within Laravel Sail...
sail php --version

# Artisan commands within Laravel Sail...
sail artisan about

# Composer commands within Laravel Sail...
sail composer install

# NPM commands within Laravel Sail...
sail npm run dev
```

To interact with databases (MariaDB, Redis...), please refer to the Laravel Sail documentation

### Building JS/CSS assets

Yarn is required, run:

```bash
make build-assets
```

This will build all of the assets according to the webpack file.

---

## Contributors

Thank you to everyone who've contributed to phpVMS!

<a href="https://github.com/phpvms/phpvms/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=phpvms/phpvms" />
</a>
