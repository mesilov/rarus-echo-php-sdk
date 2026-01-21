# This file is part of the rarus-echo-php-sdk package.
#
#  For the full copyright and license information, please view the LICENSE.txt
#  file that was distributed with this source code.
#!/usr/bin/env make

export COMPOSE_HTTP_TIMEOUT=120
export DOCKER_CLIENT_TIMEOUT=120

.DEFAULT_GOAL := help

%:
	@: # silence

# load default and personal env-variables
ENV := $(PWD)/.env
ENV_LOCAL := $(PWD)/.env.local
include $(ENV)
-include $(ENV_LOCAL)


help:
	@echo "-------------------------"
	@echo "    Rarus Echo PHP SDK   "
	@echo "-------------------------"
	@echo ""
	@echo "docker-init               - first installation"
	@echo "docker-up                 - run docker"
	@echo "docker-down               - stop docker"
	@echo "docker-down-clear         - stop docker and remove orphaned containers"
	@echo "docker-pull               - download images and ignore pull failures"
	@echo "docker-restart            - restart containers"
	@echo "docker-rebuild            - build containers without use local cache"
	@echo ""
	@echo "composer-install          - install dependencies from composer"
	@echo "composer-update           - update dependencies from composer"
	@echo "composer-dumpautoload     - regenerate composer autoload file"
	@echo "composer                  - run composer and pass arguments"
	@echo ""
	@echo "show-env                  - show environment variables from .env files"
	@echo ""

.PHONY: docker-init
docker-init: ## Initial Docker setup (build, start, install dependencies)
	docker compose build
	docker compose run --rm php-cli composer install
	@echo "Docker environment initialized successfully!"

.PHONY: docker-up
docker-up: ## Start Docker containers
	docker compose up -d

.PHONY: docker-down
docker-down: ## Stop Docker containers
	docker compose down

.PHONY: docker-down-clear
docker-down-clear: ## Stop containers and remove volumes
	docker compose down -v

.PHONY: docker-restart
docker-restart: docker-down docker-up ## Restart Docker containers

.PHONY: docker-pull
docker-pull: ## Pull Docker images
	docker compose pull --ignore-buildable

.PHONY: docker-rebuild
docker-rebuild: ## Rebuild Docker images
	docker compose build --no-cache

# ============================================================================
# Composer Commands
# ============================================================================

# work with composer in docker container
.PHONY: composer-install
composer-install:
	@echo "install dependencies…"
	docker-compose run --rm php-cli composer install

.PHONY: composer-update
composer-update:
	@echo "update dependencies…"
	docker-compose run --rm php-cli composer update

.PHONY: composer-dumpautoload
composer-dumpautoload:
	docker-compose run --rm php-cli composer dumpautoload

.PHONY: composer
# call composer with any parameters
# make composer install
# make composer "install --no-dev"
composer:
	docker-compose run --rm php-cli composer $(filter-out $@,$(MAKECMDGOALS))



# ============================================================================
# Code Quality & Linting
# ============================================================================

.PHONY: lint-all
lint-all: lint-cs-fixer lint-phpstan lint-rector ## Run all linters

.PHONY: lint-cs-fixer
lint-cs-fixer: ## Check code style with PHP CS Fixer
	docker compose run php-cli vendor/bin/php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.dist.php --allow-risky=yes

.PHONY: lint-cs-fixer-fix
lint-cs-fixer-fix: ## Auto-fix code style with PHP CS Fixer
	docker compose run php-cli vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --allow-risky=yes

.PHONY: lint-phpstan
lint-phpstan: ## Run PHPStan static analysis
	docker compose run php-cli vendor/bin/phpstan analyse --memory-limit=1G

.PHONY: lint-rector
lint-rector: ## Check code with Rector (dry-run)
	docker compose run php-cli vendor/bin/rector process --dry-run

.PHONY: lint-rector-fix
lint-rector-fix: ## Apply Rector fixes
	docker compose run php-cli vendor/bin/rector process

# ============================================================================
# Testing
# ============================================================================

.PHONY: test-unit
test-unit: ## Run unit tests
	docker compose run php-cli vendor/bin/phpunit --testsuite=unit

# integration tests
.PHONY: test-integration
test-integration:
	docker-compose run --rm php-cli vendor/bin/phpunit --testsuite integration

# ============================================================================
# Development Tools
# ============================================================================

.PHONY: php-cli-bash
php-cli-bash: ## Access PHP CLI container shell
	docker compose exec php-cli bash

.PHONY: php-cli-root
php-cli-root: ## Access PHP CLI container as root
	docker compose exec -u root php-cli bash

.PHONY: clear-cache
clear-cache: ## Clear all caches
	docker compose exec php-cli rm -rf var/cache/* coverage/* .phpunit.cache/* .php-cs-fixer.cache