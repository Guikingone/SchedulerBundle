DOCKER_COMPOSE 			= @docker-compose

PHP            			= $(DOCKER_COMPOSE) run --rm php
COMPOSER       			= $(DOCKER_COMPOSE) run --rm php composer
MUTAGEN_COMPOSE_ENABLED := $(shell type mutagen-compose)

ifdef MUTAGEN_COMPOSE_ENABLED
	DOCKER_COMPOSE = mutagen-compose
endif

.DEFAULT_GOAL := help

help:
	@grep -E '(^[a-zA-Z_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

##
## Project
##---------------------------------------------------------------------------

.PHONY: boot up ps down vendor

boot: ## Launch the project
boot: up vendor

up: ## Up the containers
up: .cloud/docker docker-compose.yaml
	$(DOCKER_COMPOSE) up -d --build --remove-orphans --force-recreate

down: ## Up the containers
down: .cloud/docker docker-compose.yaml
	$(DOCKER_COMPOSE) down --volumes

ps: ## List the services
ps: docker-compose.yaml
	$(DOCKER_COMPOSE) ps

vendor: ## Install the dependencies
vendor: composer.json
	$(PHP) composer install

vendor-highest: ## Install the dependencies (highest version)
vendor-highest: composer.json
	$(PHP) composer update --no-interaction --no-progress --ansi --prefer-stable

vendor-lowest: ## Install the dependencies (lowest version)
vendor-lowest: composer.json
	$(PHP) composer update --prefer-lowest --no-interaction --no-progress --ansi --prefer-stable

autoload: ## Dump the autoload
autoload: composer.json composer.lock
	$(COMPOSER) dump-autoload

##
## Tools
##---------------------------------------------------------------------------

.PHONY: php-cs-fixer php-cs-fixer-dry phpstan rector-dry rector

php-cs-fixer: ## Run PHP-CS-FIXER and fix the errors
php-cs-fixer: .php-cs-fixer.dist.php
	$(PHP) vendor/bin/php-cs-fixer fix --allow-risky=yes

php-cs-fixer-dry: ## Run PHP-CS-FIXER in --dry-run mode
php-cs-fixer-dry: .php-cs-fixer.dist.php
	$(PHP) vendor/bin/php-cs-fixer fix --allow-risky=yes --dry-run

phpstan: ## Run PHPStan (the configuration must be defined in phpstan.neon.dist)
phpstan: phpstan.neon.dist
	$(PHP) vendor/bin/phpstan analyse --memory-limit 2G --xdebug

rector: rector.php
	$(PHP) vendor/bin/rector

rector-dry: rector.php
	$(PHP) vendor/bin/rector --dry-run

##
## Tests
##---------------------------------------------------------------------------

.PHONY: tests infection

tests: ## Launch the PHPUnit tests
tests: phpunit.xml.dist autoload
	$(PHP) vendor/bin/phpunit tests -v

tests-group: ## Launch the PHPUnit tests using a specific group
tests-group: phpunit.xml.dist autoload
	$(PHP) vendor/bin/phpunit tests -v --group $(GROUP)

infection: ## Launch Infection
infection: infection.json.dist autoload
	$(PHP) vendor/bin/infection --threads=4

##
## Versioning
##---------------------------------------------------------------------------

.PHONY: generate-changelog generate-changelog-release generate-changelog-major generate-changelog-minor generate-changelog-patch

generate-changelog: ## Update CHANGELOG.md
generate-changelog: .changelog
	$(PHP) vendor/bin/conventional-changelog --config .changelog

generate-changelog-release: ## Update CHANGELOG.md using the latest commits
generate-changelog-release: .changelog
	$(PHP) vendor/bin/conventional-changelog --commit --config .changelog

generate-changelog-major: ## Generate a major release
generate-changelog-major: .changelog
	$(PHP) vendor/bin/conventional-changelog --major --commit --config .changelog

generate-changelog-minor: ## Generate a minor release
generate-changelog-minor: .changelog
	$(PHP) vendor/bin/conventional-changelog --minor --commit --config .changelog

generate-changelog-patch: ## Generate a patch release
generate-changelog-patch: .changelog
	$(PHP) vendor/bin/conventional-changelog --patch --commit --config .changelog
