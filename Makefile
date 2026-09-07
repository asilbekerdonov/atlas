.PHONY: db-reset db-migrate db-fixtures

db-reset:
	@echo "🔄 Сброс базы данных..."
	php bin/console doctrine:database:drop --force --if-exists
	php bin/console doctrine:database:create
	php bin/console doctrine:migrations:migrate --no-interaction
	php bin/console doctrine:fixtures:load --no-interaction
	@echo "✅ Готово!"

db-migrate:
	php bin/console doctrine:migrations:migrate --no-interaction

db-fixtures:
	php bin/console doctrine:fixtures:load --no-interaction