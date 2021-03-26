PHP            = @symfony php

.DEFAULT_GOAL := help

help:
	@grep -E '(^[a-zA-Z_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

##
## Project
##---------------------------------------------------------------------------

.PHONY: boot up down vendor

boot: ## Launch the project
boot: vendor

vendor: ## Install the dependencies
vendor: composer.json composer.lock
	$(PHP) composer install

##
## Tools
##---------------------------------------------------------------------------

.PHONY: php-cs-fixer php-cs-fixer-dry phpstan rector-dry rector

php-cs-fixer: ## Run PHP-CS-FIXER and fix the errors
php-cs-fixer:
	$(PHP) vendor/bin/php-cs-fixer fix .

php-cs-fixer-dry: ## Run PHP-CS-FIXER in --dry-run mode
php-cs-fixer-dry:
	$(PHP) vendor/bin/php-cs-fixer fix . --dry-run

phpstan: ## Run PHPStan (the configuration must be defined in phpstan.neon)
phpstan: phpstan.neon.dist
	$(PHP) vendor/bin/phpstan analyse --memory-limit 2G --xdebug

psalm: ## Run Psalm
psalm: psalm.xml
	$(PHP) vendor/bin/psalm --show-info=true

rector-dry: ## Run Rector in --dry-run mode
rector-dry: rector.php
	$(PHP) vendor/bin/rector --dry-run --clear-cache

rector: ## Run Rector
rector: rector.php
	$(PHP) vendor/bin/rector --clear-cache

##
## Tests
##---------------------------------------------------------------------------

.PHONY: tests infection

tests: ## Launch the PHPUnit tests
tests: phpunit.xml.dist
	$(PHP) vendor/bin/phpunit tests

infection: ## Launch Infection
infection: infection.json.dist
	$(PHP) vendor/bin/infection
