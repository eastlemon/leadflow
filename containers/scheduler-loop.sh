#!/bin/sh
# Loop wrapper for the scheduler container.
# `php artisan schedule:run` is meant to be called by cron every minute;
# this loop approximates that without needing a cron daemon in the
# container. Use in dev — in prod a real cron is preferable.
set -e
cd /var/www/html
while true; do
    php artisan schedule:run --no-interaction || true
    sleep 60
done
