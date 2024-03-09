# start targets
setup: start composer-install

shell: start
	@docker-compose exec php zsh

start:
	@docker-compose up -d

# commands
composer-install:
	@docker-compose exec -T php composer install --ansi

pre-commit-checks: rector code-style-fix psalm test infection

rector:
	@docker-compose exec -T php ./vendor/bin/rector --ansi

code-style-check: start
	@docker-compose exec -T php ./vendor/bin/php-cs-fixer fix --verbose --ansi --dry-run

code-style-fix: start
	@docker-compose exec -T php ./vendor/bin/php-cs-fixer fix --verbose --ansi

psalm: start
	@docker-compose exec -T php ./vendor/bin/psalm

test:
	@docker-compose exec -T php ./vendor/bin/phpunit --colors=always

infection:
	@docker-compose exec -e XDEBUG_MODE=coverage -T php ./vendor/bin/infection --show-mutations --ansi

.SILENT:
