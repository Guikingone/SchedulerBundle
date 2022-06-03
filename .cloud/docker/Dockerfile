FROM composer:2.1 as composer
FROM php:8.0-cli

ENV SCHEDULER_REDIS_DSN 'redis://redis:6379/_symfony_scheduler_tasks'

WORKDIR /srv/app

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev=1.7 zip=3.0 redis=5.0 git=2.30 \
    && pecl install redis xdebug \
    && docker-php-ext-install pcntl zip \
    && docker-php-ext-enable pcntl xdebug redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY . .
