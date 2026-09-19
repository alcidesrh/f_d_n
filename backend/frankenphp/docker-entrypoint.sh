#!/bin/sh
set -e

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
	if [ -z "$(ls -A 'vendor/' 2>/dev/null)" ]; then
		composer install --prefer-dist --no-progress --no-interaction
	fi

	if [ "$APP_ENV" != 'prod' ]; then
		php bin/console -V
	fi

	if [ -n "${DATABASE_URL:-}" ] || grep -q ^DATABASE_URL= .env 2>/dev/null; then
		echo 'Waiting for database to be ready...'
		ATTEMPTS_LEFT_TO_REACH_DATABASE=60
		until [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ] || DATABASE_ERROR=$(php bin/console dbal:run-sql -q "SELECT 1" 2>&1); do
			# Retry for the full window: transient failures (e.g. "Temporary failure
			# in name resolution" reported as exit 255) must not abort startup early.
			sleep 1
			ATTEMPTS_LEFT_TO_REACH_DATABASE=$((ATTEMPTS_LEFT_TO_REACH_DATABASE - 1))
		done

		if [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ]; then
			echo 'The database is not up or not reachable:'
			echo "$DATABASE_ERROR"
			exit 1
		else
			echo 'The database is now ready and reachable'
		fi

		# Run schema migrations only on a legacy database (one that still has the old
		# "salida" table). Modern databases are built directly from the Doctrine
		# entities (schema:create via app:migrar:todo --clean / app:reset-db2 --hard);
		# the committed migrations rename salida->servicio and would fail elsewhere.
		# if php bin/console dbal:run-sql -q "SELECT 1 FROM salida WHERE false" >/dev/null 2>&1 && [ -n "$(find ./migrations -iname '*.php' -print -quit)" ]; then
		# 	php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing
		# else
		# 	echo 'No legacy schema detected: skipping schema migrations.'
		# fi
	fi

	echo 'PHP app ready!'
fi

exec docker-php-entrypoint "$@"
