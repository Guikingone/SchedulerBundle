PHP            = @symfony php
COMPOSER       = @symfony composer

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

autoload: ## Dump the autoload
autoload: composer.json
	$(COMPOSER) dump-autoload

##
## Tools
##---------------------------------------------------------------------------

.PHONY: php-cs-fixer php-cs-fixer-dry phpstan rector-dry rector

php-cs-fixer: ## Run PHP-CS-FIXER and fix the errors
php-cs-fixer:
	$(PHP) vendor/bin/php-cs-fixer fix --allow-risky=yes

php-cs-fixer-dry: ## Run PHP-CS-FIXER in --dry-run mode
php-cs-fixer-dry:
	$(PHP) vendor/bin/php-cs-fixer fix --allow-risky=yes --dry-run

phpstan: ## Run PHPStan (the configuration must be defined in phpstan.neon)
phpstan: phpstan.neon.dist
	$(PHP) vendor/bin/phpstan analyse --memory-limit 2G --xdebug

psalm: ## Run Psalm
psalm: psalm.xml
	$(PHP) vendor/bin/psalm

static-analysis: ## Launch the static analysis tools
static-analysis: phpstan psalm

psalm-debug: ## Run Psalm (display informations)
psalm-debug: psalm.xml
	$(PHP) vendor/bin/psalm --show-info=true

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

infection: ## Launch Infection
infection: infection.json.dist autoload
	$(PHP) vendor/bin/infection

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
