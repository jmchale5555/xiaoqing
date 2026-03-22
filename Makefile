.PHONY: test test-feature test-unit test-integration

test:
	docker compose exec -T php vendor/bin/phpunit

test-feature:
	docker compose exec -T php vendor/bin/phpunit --testsuite Feature

test-unit:
	docker compose exec -T php vendor/bin/phpunit --testsuite Unit

test-integration:
	docker compose exec -T php vendor/bin/phpunit --testsuite Integration
