.PHONY: up reup rebuild init lint stan test chain

project=symfony-span-bundle
env-file=.env

dev_compose=docker-compose -f ./docker-compose.yml --env-file $(env-file)
dev_compose_exec=$(dev_compose) exec -it -u www-data php bash -c

up:
	$(dev_compose) up -d

reup:
	$(dev_compose) down
	$(dev_compose) up -d

rebuild:
	$(dev_compose) build
	$(dev_compose) down
	$(dev_compose) up -d

sh:
	$(dev_compose) exec -it -u www-data php bash

shr:
	$(dev_compose) exec -it -u root php bash

cc:
	@rm -rf ./../var/cache

init:
	@set -eu; \
	set -a; . $(env-file); set +a; \
	$(dev_compose_exec) "rm -f composer.lock"; \
	$(dev_compose_exec) "composer require --no-update --no-progress --quiet \
	symfony/config=^$$SYMFONY_VERSION \
	symfony/dependency-injection=^$$SYMFONY_VERSION \
	symfony/event-dispatcher=^$$SYMFONY_VERSION" \
	; \
	$(dev_compose_exec) "composer require --dev --no-update --no-progress --quiet \
	doctrine/dbal=^$$DBAL_VERSION \
	guzzlehttp/guzzle=^$$GUZZLE_VERSION \
	symfony/amqp-messenger=^$$SYMFONY_VERSION \
	symfony/console=^$$SYMFONY_VERSION \
	symfony/doctrine-messenger=^$$SYMFONY_VERSION \
	symfony/framework-bundle=^$$SYMFONY_VERSION \
	symfony/http-client=^$$SYMFONY_VERSION \
	symfony/http-foundation=^$$SYMFONY_VERSION \
	symfony/http-kernel=^$$SYMFONY_VERSION \
	symfony/messenger=^$$SYMFONY_VERSION \
	symfony/redis-messenger=^$$SYMFONY_VERSION" \
	; \
	$(dev_compose_exec) "composer update -n -W --prefer-dist --no-progress --no-cache"
	@git restore ../composer.json
	@make cc

lint:
	$(dev_compose_exec) 'vendor/bin/phpcs'

stan:
	@$(dev_compose_exec) 'vendor/bin/phpstan analyze -c ./dev/phpstan.neon --memory-limit=512M'

test:
	@$(dev_compose_exec) 'composer dump-autoload -o -n --quiet'
	@$(dev_compose_exec) 'vendor/bin/codecept build -n --quiet'
	@$(dev_compose_exec) 'APP_ENV=test TEST_TOKEN=main vendor/bin/codecept run functional -n --no-ansi --no-artifacts --no-rebuild'

chain: cc test
