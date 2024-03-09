WAIT=docker compose up --wait --no-deps

# start targets
setup: start composer-install

shell: wait-php
	@docker compose exec php zsh

start:
	@docker compose up -d

wait-php:
	@$(WAIT) php

wait-mysql:
	@$(WAIT) mysql

# commands
composer-install: wait-php
	@docker compose exec -T php composer install --ansi

pre-commit-checks: rector code-style-fix psalm test infection

rector: wait-php
	@docker compose exec -T php ./vendor/bin/rector --ansi

code-style-check: wait-php
	@docker compose exec -T php ./vendor/bin/php-cs-fixer fix --verbose --ansi --dry-run

code-style-fix: wait-php
	@docker compose exec -T php ./vendor/bin/php-cs-fixer fix --verbose --ansi

psalm: wait-php
	@docker compose exec -T php ./vendor/bin/psalm

test: wait-php wait-mysql
	@docker compose exec -T php ./vendor/bin/phpunit --colors=always

infection: wait-php wait-mysql
	@docker compose exec -e XDEBUG_MODE=coverage -T php ./vendor/bin/infection --show-mutations --ansi

.SILENT:
