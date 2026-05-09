.DEFAULT_GOAL := help

GREEN  := $(shell tput -Txterm setaf 2)
WHITE  := $(shell tput -Txterm setaf 7)
YELLOW := $(shell tput -Txterm setaf 3)
RESET  := $(shell tput -Txterm sgr0)

COMPOSE=docker compose --env-file .env
PHP_SERVICE=php
EXEC=$(COMPOSE) exec -T $(PHP_SERVICE)
EXEC_TTY=$(COMPOSE) exec $(PHP_SERVICE)
ARTISAN=$(EXEC) php artisan

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

test: env-init ##@test Run tests
	$(EXEC) php artisan test

pint: env-init ##@test Check code style
	$(EXEC) vendor/bin/pint --test

filament-assets: env-init ##@laravel Publish Filament assets
	$(ARTISAN) filament:assets

queue: env-init ##@commands Run queue worker
	$(ARTISAN) queue:work database --queue=default --sleep=3 --tries=1 --timeout=600

schedule: env-init ##@commands Run scheduler
	$(ARTISAN) schedule:work

vk-listen: env-init ##@commands Run VK Long Poll listener
	$(ARTISAN) vk:listen

browser-login: env-init ##@commands Start noVNC browser for manual Ozon login
	$(COMPOSE) --profile browser-login up browser-login
