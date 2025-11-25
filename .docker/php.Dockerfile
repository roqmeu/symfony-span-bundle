ARG PHP_VERSION

FROM ghcr.io/roqmeu/php${PHP_VERSION?}:latest

RUN set -eux; \
	pecl install excimer-1.2.5; \
	docker-php-ext-enable excimer --ini-name /usr/local/etc/php/conf.d/docker-php-ext-excimer.ini

COPY var/apm-agent-php_1.15.1_arm64.deb /tmp/apm-agent-php.deb

ENV ELASTIC_APM_DISABLE_SEND='true'
ENV ELASTIC_APM_DISABLE_INSTRUMENTATIONS='*'

RUN set -eux; \
	apt-get update; \
	apt-get install -y /tmp/apm-agent-php.deb; \
	rm -f /tmp/apm-agent-php.deb; \
	rm -rf /var/lib/apt/lists/*
