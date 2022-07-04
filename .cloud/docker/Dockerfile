FROM composer:2.1 as composer
FROM php:8.0-cli

ENV SCHEDULER_POSTGRES_DSN 'postgres://toor:root@postgres:5432/_symfony_scheduler_tasks&charset=utf8'
ENV SCHEDULER_REDIS_DSN 'redis://redis:6379/_symfony_scheduler_tasks'

WORKDIR /srv/app

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev=1.7.3-1 zip=3.0-12 redis=5:6.0.16-1+deb11u2 git=1:2.30.2-1 libpq-dev=13.7-0+deb11u1 \
    && pecl install redis xdebug \
    && docker-php-ext-install pcntl zip pdo_pgsql \
    && docker-php-ext-enable pcntl xdebug redis pdo_pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY . .
