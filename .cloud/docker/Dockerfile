FROM composer as composer
FROM php:7.4-cli

ENV SCHEDULER_REDIS_DSN 'redis://redis:6379/_symfony_scheduler_tasks'

WORKDIR /srv/app

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev zip redis git \
    && pecl install redis xdebug \
    && docker-php-ext-install pcntl zip \
    && docker-php-ext-enable pcntl xdebug redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY . .
