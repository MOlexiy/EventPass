#!/bin/sh
# Prepares the app on first start, then runs the given command.
set -e

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ -z "$APP_KEY" ] && ! grep -q "^APP_KEY=base64" .env; then
    php artisan key:generate --force
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    # Wait for PostgreSQL, then migrate. The seeder skips itself if demo data exists.
    until php artisan migrate --force --isolated; do
        echo "Waiting for the database..."
        sleep 2
    done
    php artisan db:seed --force
fi

exec "$@"
