#!make

.PHONY: up reup rebi init lint stan test chain

project=symfony-span-bundle
env-file=.env

dev_compose=docker-compose -f ./docker-compose.yml --env-file $(env-file)
dev_compose_exec=$(dev_compose) exec -it -u www-data php bash -c

up:
	$(dev_compose) up -d --remove-orphans

reup:
	$(dev_compose) down --remove-orphans
	$(dev_compose) up -d --force-recreate

rebi:
	$(dev_compose) build
	$(dev_compose) down --remove-orphans
	$(dev_compose) up -d

sh:
	$(dev_compose) exec -it -u www-data php bash

shr:
	$(dev_compose) exec -it -u root php bash

cc:
	@rm -rf ./var/cache

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
	php-amqplib/rabbitmq-bundle=^$$RABBITMQ_BUNDLE_VERSION \
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
	@git restore ./composer.json
	@make cc
	@make reup

lint:
	@$(dev_compose_exec) "vendor/bin/ecs check $(LINT_FLAGS) -c ./ecs.php  --memory-limit=512M --no-progress-bar"

stan:
	@$(dev_compose_exec) "ELASTIC_APM_ENABLED='false' vendor/bin/phpstan analyze $(STAN_FLAGS) -c ./phpstan.neon --memory-limit=512M"

test:
	@$(dev_compose_exec) 'APP_ENV=test composer clear-cache -n --quiet'
	@$(dev_compose_exec) 'APP_ENV=test composer dump-autoload --dev -o -a -n --quiet'
	@$(dev_compose_exec) 'APP_ENV=test TEST_TOKEN=test vendor/bin/codecept build -n --quiet'
	@$(dev_compose_exec) 'APP_ENV=test TEST_TOKEN=test vendor/bin/codecept run functional -n --no-artifacts --no-rebuild'

chain: cc lint stan test
