.DEFAULT_GOAL := help

GREEN  := $(shell tput -Txterm setaf 2)
WHITE  := $(shell tput -Txterm setaf 7)
YELLOW := $(shell tput -Txterm setaf 3)
RESET  := $(shell tput -Txterm sgr0)

COMPOSE=docker compose --env-file .env
TEST_COMPOSE=docker compose --env-file .env.test -p ozonprices_test -f docker-compose.test.yml
PHP_SERVICE=php
EXEC=$(COMPOSE) exec -T $(PHP_SERVICE)
EXEC_TTY=$(COMPOSE) exec $(PHP_SERVICE)
ARTISAN=$(EXEC) php artisan
TEST_EXEC=$(TEST_COMPOSE) exec -T $(PHP_SERVICE)
TEST_ARTISAN=$(TEST_EXEC) php artisan
HOST_UID=$(shell id -u)
HOST_GID=$(shell id -g)
OZON_PROFILE_PATH=/var/www/ozon-prices/storage/app/ozon-browser-profile

HELP_FUN = \
	%help; \
	while(<>) { push @{$$help{$$2 // 'other'}}, [$$1, $$3] if /^([a-zA-Z\-]+)\s*:.*\#\#(?:@([a-zA-Z\-]+))?\s(.*)$$/ }; \
	print "usage: make [command]\n\n"; \
	for (sort keys %help) { \
	print "${WHITE}$$_:${RESET}\n"; \
	for (@{$$help{$$_}}) { \
	$$sep = " " x (32 - length $$_->[0]); \
	print "  ${YELLOW}$$_->[0]${RESET}$$sep${GREEN}$$_->[1]${RESET}\n"; \
	}; \
	print "\n"; }

help: ##@other Show this help.
	@perl -e '$(HELP_FUN)' $(MAKEFILE_LIST)

env-init: ##@env Create .env from .env.example if missing
	test -f .env || cp .env.example .env

test-env-init: ##@env Create .env.test from .env.test.example if missing
	test -f .env.test || cp .env.test.example .env.test

build: env-init ##@container Build and start project
	$(COMPOSE) build php nginx
	$(COMPOSE) up -d --no-build --remove-orphans
	$(EXEC) composer install -n
	$(EXEC) vendor/bin/playwright-install --browsers
	$(ARTISAN) key:generate --ansi --force
	$(ARTISAN) filament:assets
	$(ARTISAN) migrate --force

up: env-init ##@container Start containers
	$(COMPOSE) up -d --no-build

down: env-init ##@container Stop containers
	$(COMPOSE) down

ps: env-init ##@container Show container status
	$(COMPOSE) ps

bash: env-init ##@container Open bash in PHP container
	$(EXEC_TTY) bash

migrate: env-init ##@laravel Run migrations
	$(ARTISAN) migrate

test-up: test-env-init ##@test Start test containers
	$(TEST_COMPOSE) up -d --build --remove-orphans

test-down: test-env-init ##@test Stop test containers
	$(TEST_COMPOSE) down

test-composer-install: test-up ##@test Install dependencies in test container
	$(TEST_EXEC) composer install -n

test-migrate: test-composer-install ##@test Run test migrations
	$(TEST_ARTISAN) migrate --force

test: test-migrate ##@test Run tests
	$(TEST_ARTISAN) test

pint: test-composer-install ##@test Check code style
	$(TEST_EXEC) vendor/bin/pint --test

filament-assets: env-init ##@laravel Publish Filament assets
	$(ARTISAN) filament:assets

admin-create: env-init ##@commands Create Filament admin user
	$(EXEC_TTY) php artisan admin:create

queue: env-init ##@commands Run queue worker
	$(ARTISAN) queue:work database --queue=default --sleep=3 --tries=1 --timeout=600

schedule: env-init ##@commands Run scheduler
	$(ARTISAN) schedule:work

vk-listen: env-init ##@commands Run VK Long Poll listener
	$(ARTISAN) vk:listen

browser-profile-perms: env-init
	$(COMPOSE) run --rm --user root --no-deps $(PHP_SERVICE) sh -lc 'mkdir -p "$(OZON_PROFILE_PATH)" && chown -R $(HOST_UID):$(HOST_GID) "$(OZON_PROFILE_PATH)"'

browser-login: env-init browser-profile-perms ##@commands Start noVNC browser for manual Ozon login
	$(COMPOSE) --profile browser-login up browser-login
