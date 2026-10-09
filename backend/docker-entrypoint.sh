#!/bin/sh
# Prepares the app on container start, then runs the given command.
set -e

# `php artisan serve` passes only a few variables (APP_ENV, PATH, ...) to the
# PHP server process it spawns, so settings from docker-compose would be lost.
# Write them into the container's own .env, which that process reads.
php -r '
$prefixes = ["APP_", "DB_", "REDIS_", "CACHE_", "QUEUE_", "SESSION_", "MAIL_", "SANCTUM_",
    "FRONTEND_", "PAYMENT_", "WEBHOOK_", "ORDER_", "LIQPAY_", "STRIPE_", "GOOGLE_", "LOG_"];
$lines = [];
foreach (getenv() as $key => $value) {
    foreach ($prefixes as $prefix) {
        if (str_starts_with($key, $prefix)) {
            $lines[] = $key."=\"".addcslashes($value, "\"\\")."\"";
            break;
        }
    }
}
sort($lines);
file_put_contents(".env", implode(PHP_EOL, $lines).PHP_EOL);
'

if ! grep -q '^APP_KEY="base64' .env; then
    echo 'APP_KEY=' >> .env
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

# Single-container hosting (e.g. Render free tier): run the queue worker and
# the scheduler in the background next to the web server.
if [ "${RUN_WORKERS:-false}" = "true" ]; then
    php artisan queue:work --tries=3 --sleep=3 &
    php artisan schedule:work &
fi

exec "$@"
