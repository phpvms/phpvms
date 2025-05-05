#
#
# Create the phpvms database if needed:
# docker exec phpvms /usr/bin/mysql -uroot -e 'CREATE DATABASE phpvms'
SHELL := /bin/bash
COMPOSER ?= $(shell which composer)

PKG_NAME := "/tmp"
CURR_PATH=$(shell pwd)

.PHONY: all
all: install

.PHONY: clean
clean:
	@php artisan cache:clear
	@php artisan route:clear
	@php artisan config:clear
	@php artisan view:clear
	@find bootstrap/cache -type f -not -name '.gitignore' -print0 | xargs -0 rm -rf

	@find storage/framework/cache/ -mindepth 1 -type f -not -name '.gitignore' -print0 | xargs -0 rm -rf
	@find storage/framework/sessions/ -mindepth 1 -type f -not -name '.gitignore' -print0 | xargs -0 rm -rf
	@find storage/framework/views/ -mindepth 1 -not -name '.gitignore' -print0 | xargs -0 rm -rf

	@find storage/logs -mindepth 1 -not -name '.gitignore' -print0 | xargs -0 rm -rf

.PHONY: clean-routes
clean-routes:
	@php artisan route:clear

.PHONY: clear
clear:
	@php artisan cache:clear
	@php artisan config:clear
	@php artisan route:clear
	@php artisan view:clear

.PHONY:  build
build:
	@php $(COMPOSER) install --no-interaction

# This is to build all the stylesheets, etc
.PHONY: build-assets
build-assets:
	npm run production

.PHONY: install
install: build
	@php artisan database:create
	@php artisan migrate --seed
	@echo "Done!"

.PHONY: update
update: build
	@php $(COMPOSER) dump-autoload
	@php $(COMPOSER) update --no-interaction
	@php artisan migrate --force
	@echo "Done!"

.PHONY: reset
reset: clean
	@php $(COMPOSER) dump-autoload
	@make reload-db

.PHONY: reload-db
reload-db:
	@php artisan database:create --reset
	@php artisan migrate --seed
	@echo "Done!"
	@make clean

.PHONY: tests
tests: test

.PHONY: test
test:
	@#php artisan database:create --reset
	@vendor/bin/phpunit

.PHONY: phpcs
phpcs:
	@PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php -v --dry-run --diff --using-cache=no

.PHONY: replay-acars
replay-acars:
	#@php artisan phpvms:replay AAL10,AAL3113,BAW172,DAL988,FIN6,MSR986 --manual
	@php artisan phpvms:replay ASH6028 --manual

.PHONY: sass-watch
sass-watch:
	sass --watch public/assets/admin/sass/paper-dashboard.scss:public/assets/admin/css/paper-dashboard.css

.PHONY: deploy-package
deploy-package:
	./.travis/deploy_script.sh

.PHONY: reset-installer
reset-installer:
	@php artisan database:create --reset
	@php artisan migrate:refresh --seed

.PHONY: docker-test
docker-test:
	@docker run --rm \
	    -u $(shell id -u):$(shell id -g) \
	    -v $(shell pwd):/var/www/html \
	    -w /var/www/html \
	    laravelsail/php84-composer:latest \
	    composer install --ignore-platform-reqs
	@vendor/bin/sail up

.PHONY: docker-clean
docker-clean:
	-docker stop phpvms
	-docker rm -rf phpvms
	-rm core/local.config.php
	-rm -rf tmp/mysql
