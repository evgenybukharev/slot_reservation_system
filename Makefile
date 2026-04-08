# Makefile for Laravel Docker Environment

# Variables
DC = docker compose
APP_EXEC = $(DC) exec app

.PHONY: up down restart migrate seed cache flush shell help

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

up: ## Start containers
	$(DC) up -d

down: ## Stop containers
	$(DC) down

restart: down up ## Restart containers

migrate: ## Run migrations
	$(APP_EXEC) php artisan migrate

seed: ## Run seeders
	$(APP_EXEC) php artisan db:seed

cache: ## Clear cache (config, route, view, cache)
	$(APP_EXEC) php artisan optimize:clear

flush: ## Comprehensive cleanup (cache + optimize:clear)
	$(APP_EXEC) php artisan optimize:clear
	$(APP_EXEC) php artisan cache:clear

schedule: ## Run scheduler once (for testing)
	$(APP_EXEC) php artisan schedule:run

schedule-work: ## Start scheduler daemon (blocking)
	$(APP_EXEC) php artisan schedule:work

shell: ## Open shell in app container
	$(APP_EXEC) bash
