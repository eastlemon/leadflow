#!/bin/sh
# Container entrypoint for LeadFlow php-fpm / horizon / scheduler.
#
# - Waits for MySQL and Redis to accept connections.
# - Ensures storage/ and bootstrap/cache/ are writable.
# - Generates APP_KEY if missing (first boot).
# - Runs pending migrations on first start (idempotent).
# - Execs the command from CMD (php-fpm, horizon, etc.)

set -e

cd /var/www/html

# --- Storage perms (best effort) -----------------------------------------
chmod -R 777 storage bootstrap/cache 2>/dev/null || true

# --- Wait for MySQL -------------------------------------------------------
if [ -n "${DB_HOST:-}" ]; then
    echo "[entrypoint] waiting for MySQL at ${DB_HOST}:${DB_PORT:-3306}..."
    for _ in $(seq 1 60); do
        if mysqladmin ping \
            -h"${DB_HOST}" -P"${DB_PORT:-3306}" \
            -u"${DB_USERNAME:-root}" "-p${DB_PASSWORD:-root_secret}" \
            --connect-timeout=2 --silent 2>/dev/null
        then
            echo "[entrypoint] MySQL is up."
            break
        fi
        sleep 1
    done
fi

# --- Wait for Redis -------------------------------------------------------
if [ -n "${REDIS_HOST:-}" ]; then
    echo "[entrypoint] waiting for Redis at ${REDIS_HOST}:${REDIS_PORT:-6379}..."
    for _ in $(seq 1 30); do
        if timeout 2 bash -c "</dev/tcp/${REDIS_HOST}/${REDIS_PORT:-6379}" 2>/dev/null; then
            echo "[entrypoint] Redis is up."
            break
        fi
        sleep 1
    done
fi

# --- Generate APP_KEY if missing -----------------------------------------
if [ -z "${APP_KEY:-}" ] || [ "${APP_KEY:-}" = "base64:" ]; then
    echo "[entrypoint] generating APP_KEY..."
    php artisan key:generate --force
fi

# --- Optional first-boot migrations --------------------------------------
if [ "${RUN_MIGRATIONS:-true}" = "true" ] && [ -n "${DB_HOST:-}" ]; then
    echo "[entrypoint] running migrations..."
    php artisan migrate --force --no-interaction || {
        echo "[entrypoint] migrate failed (non-fatal -- service still starting)"
    }
fi

# --- Optional production caches ------------------------------------------
if [ "${APP_ENV:-local}" = "production" ]; then
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

echo "[entrypoint] starting: $*"
exec "$@"