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
	@echo "test-unit                 - run unit tests"
	@echo "test-integration          - run live integration tests"
	@echo "test-integration-core     - run core integration tests"
	@echo "test-integration-queue    - run queue integration tests"
	@echo "test-integration-status   - run status integration tests"
	@echo "test-integration-transcription - run transcription integration tests"
	@echo "test-all                  - run unit and integration tests"
	@echo "ci                        - run local CI checks"
	@echo "lint-agent-plugins        - validate agent plugin manifests and CLI references"
	@echo ""
	@echo "show-env                  - show environment variables from .env files"
	@echo ""
	@echo "worktree-new              - create a prepared per-issue worktree in .worktree/"
	@echo "                            (ISSUE=<n> SLUG=<slug> [TYPE=feature|bugfix|docs] [BASE=dev])"
	@echo "worktree-remove           - remove a per-issue worktree, keep the branch"
	@echo "                            (ISSUE=<n> | NAME=<n>-<slug>) [FORCE=1]"
	@echo "worktree-list             - list active per-issue worktrees"
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
	docker compose run --rm php-cli composer install

.PHONY: composer-update
composer-update:
	@echo "update dependencies…"
	docker compose run --rm php-cli composer update

.PHONY: composer-dumpautoload
composer-dumpautoload:
	docker compose run --rm php-cli composer dumpautoload

.PHONY: composer
# call composer with any parameters
# make composer install
# make composer "install --no-dev"
composer:
	docker compose run --rm php-cli composer $(filter-out $@,$(MAKECMDGOALS))



# ============================================================================
# Code Quality & Linting
# ============================================================================

.PHONY: lint-all
lint-all: lint-openspec lint-agent-plugins lint-php ## Run all linters

.PHONY: lint-agent-plugins
lint-agent-plugins: ## Validate agent plugin manifests, skills, and CLI references
	.agent-plugins/rarus-echo-transcription/scripts/validate-agent-plugin.sh

.PHONY: lint-php
lint-php: lint-cs-fixer lint-phpstan lint-rector ## Run PHP linters

.PHONY: lint-openspec
lint-openspec: ## Validate OpenSpec changes and specs
	openspec validate --all --strict --no-interactive

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
	docker compose run php-cli vendor/bin/phpunit --testsuite=unit --no-coverage

# integration tests
.PHONY: test-integration
test-integration:
	docker compose run --rm php-cli vendor/bin/phpunit --testsuite integration --no-coverage

.PHONY: test-integration-core
test-integration-core:
	docker compose run --rm php-cli vendor/bin/phpunit tests/Integration/Core --no-coverage

.PHONY: test-integration-queue
test-integration-queue:
	docker compose run --rm php-cli vendor/bin/phpunit tests/Integration/Services/Queue --no-coverage

.PHONY: test-integration-status
test-integration-status:
	docker compose run --rm php-cli vendor/bin/phpunit tests/Integration/Services/Status --no-coverage

.PHONY: test-integration-transcription
test-integration-transcription:
	docker compose run --rm php-cli vendor/bin/phpunit tests/Integration/Services/Transcription --no-coverage

.PHONY: test-all
test-all: test-unit test-integration

.PHONY: ci
ci: lint-all test-unit

# ============================================================================
# Worktree Management (parallel per-issue task work)
# ============================================================================

# Default branch type and base for a new worktree; override on the command line.
TYPE ?= feature
BASE ?= dev

.PHONY: worktree-new
# make worktree-new ISSUE=29 SLUG=parallel-worktree-tooling [TYPE=feature|bugfix|docs] [BASE=dev]
worktree-new: ## Create a prepared per-issue worktree in .worktree/
	bin/worktree.sh new --issue "$(ISSUE)" --slug "$(SLUG)" --type "$(TYPE)" --base "$(BASE)"

.PHONY: worktree-remove
# make worktree-remove ISSUE=29    (or NAME=29-slug) [FORCE=1]
worktree-remove: ## Remove a per-issue worktree (keeps the branch)
	bin/worktree.sh remove $(if $(NAME),--name "$(NAME)") $(if $(ISSUE),--issue "$(ISSUE)") $(if $(FORCE),--force)

.PHONY: worktree-list
worktree-list: ## List active per-issue worktrees
	bin/worktree.sh list

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
