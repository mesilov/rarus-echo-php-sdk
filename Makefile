# Rarus Echo PHP SDK - Makefile
# MIT License

.PHONY: help
help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Available targets:'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  \033[36m%-30s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

# ============================================================================
# Docker Commands
# ============================================================================

.PHONY: docker-init
docker-init: ## Initial Docker setup (build, start, install dependencies)
	docker compose build
	docker compose up -d
	docker compose exec php-cli composer install
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

.PHONY: composer-install
composer-install: ## Install composer dependencies
	docker compose exec php-cli composer install

.PHONY: composer-update
composer-update: ## Update composer dependencies
	docker compose exec php-cli composer update

.PHONY: composer-dumpautoload
composer-dumpautoload: ## Regenerate autoload files
	docker compose exec php-cli composer dump-autoload

.PHONY: composer
composer: ## Execute custom composer command (use: make composer cmd="require vendor/package")
	docker compose exec php-cli composer $(cmd)

# ============================================================================
# Code Quality & Linting
# ============================================================================

.PHONY: lint-all
lint-all: lint-cs-fixer lint-phpstan lint-rector ## Run all linters

.PHONY: lint-cs-fixer
lint-cs-fixer: ## Check code style with PHP CS Fixer
	docker compose exec php-cli vendor/bin/php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.dist.php

.PHONY: lint-cs-fixer-fix
lint-cs-fixer-fix: ## Auto-fix code style with PHP CS Fixer
	docker compose exec php-cli vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php

.PHONY: lint-phpstan
lint-phpstan: ## Run PHPStan static analysis
	docker compose exec php-cli vendor/bin/phpstan analyse --memory-limit=1G

.PHONY: lint-rector
lint-rector: ## Check code with Rector (dry-run)
	docker compose exec php-cli vendor/bin/rector process --dry-run

.PHONY: lint-rector-fix
lint-rector-fix: ## Apply Rector fixes
	docker compose exec php-cli vendor/bin/rector process

# ============================================================================
# Testing
# ============================================================================

.PHONY: test-unit
test-unit: ## Run unit tests
	docker compose exec php-cli vendor/bin/phpunit --testsuite=unit

.PHONY: test-integration
test-integration: ## Run integration tests
	docker compose exec php-cli vendor/bin/phpunit --testsuite=integration

.PHONY: test-all
test-all: test-unit test-integration ## Run all tests

.PHONY: test-coverage
test-coverage: ## Generate code coverage report
	docker compose exec php-cli vendor/bin/phpunit --coverage-html coverage

.PHONY: test-integration-transcription
test-integration-transcription: ## Test transcription service
	docker compose exec php-cli vendor/bin/phpunit --testsuite=integration --filter=TranscriptionTest

.PHONY: test-integration-status
test-integration-status: ## Test status service
	docker compose exec php-cli vendor/bin/phpunit --testsuite=integration --filter=StatusTest

.PHONY: test-integration-queue
test-integration-queue: ## Test queue service
	docker compose exec php-cli vendor/bin/phpunit --testsuite=integration --filter=QueueTest

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

# ============================================================================
# Documentation
# ============================================================================

.PHONY: docs-generate
docs-generate: ## Generate API documentation
	@echo "Documentation generation not implemented yet"

# ============================================================================
# CI/CD Simulation
# ============================================================================

.PHONY: ci
ci: composer-install lint-all test-all ## Run full CI pipeline locally

.PHONY: pre-commit
pre-commit: lint-cs-fixer-fix lint-phpstan test-unit ## Run pre-commit checks
