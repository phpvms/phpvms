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
	@php artisan optimize:clear
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
	@php artisan optimize:clear

.PHONY:  build
build:
	@php $(COMPOSER) install --no-interaction

# This is to build all the stylesheets, etc
.PHONY: build-assets
build-assets:
	npm run build

.PHONY: install
install: build
	@php artisan db:create
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
	@php artisan db:create --reset
	@php artisan migrate --seed
	@echo "Done!"
	@make clean

.PHONY: tests
tests: test

.PHONY: test
test:
	@#php artisan db:create --reset
	@php artisan test -p

.PHONY: pint
pint:
	@vendor/bin/pint --parallel

.PHONY: reset-installer
reset-installer:
	@php artisan db:create --reset
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
	-rm -rf tmp/mysql
