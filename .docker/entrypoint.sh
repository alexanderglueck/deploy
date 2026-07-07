#!/bin/sh
set -e

# Bind-mounted storage starts out empty; make sure Laravel's skeleton exists.
mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

# Run pending migrations before starting, so updating the app is just pulling
# a new image. --isolated takes a cache lock, so when the app and the worker
# container start simultaneously only one of them migrates.
if [ "${AUTO_MIGRATE:-0}" = "1" ] || [ "${AUTO_MIGRATE:-0}" = "true" ]; then
    php artisan migrate --force --isolated
fi

# Chain into the PHP image entrypoint: it starts FrankenPHP when given server
# flags (the default CMD) and execs anything else (e.g. php artisan queue:work).
exec docker-php-entrypoint "$@"
