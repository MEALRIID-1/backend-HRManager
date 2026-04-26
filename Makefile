.PHONY: up down migrate seed test lint

up:
	docker-compose up -d

down:
	docker-compose down

migrate:
	docker-compose exec app php artisan migrate

seed:
	docker-compose exec app php artisan db:seed

test:
	docker-compose exec app php artisan test

lint:
	docker-compose exec app ./vendor/bin/pint