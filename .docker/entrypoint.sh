#!/bin/sh
set -e

# Run pending migrations before starting, so updating the app is just pulling
# a new image. --isolated takes a cache lock, so when the app and the worker
# container start simultaneously only one of them migrates.
if [ "${AUTO_MIGRATE:-0}" = "1" ] || [ "${AUTO_MIGRATE:-0}" = "true" ]; then
    php artisan migrate --force --isolated
fi

exec "$@"
