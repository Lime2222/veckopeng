#!/bin/sh
set -e

# Wait for PostgreSQL
echo "Waiting for PostgreSQL at ${DB_HOST:-postgres}:${DB_PORT:-5432}..."
until pg_isready -h "${DB_HOST:-postgres}" -p "${DB_PORT:-5432}" -U "${DB_USER:-veckopeng}" -q; do
    sleep 1
done
echo "PostgreSQL is ready."

# Run migrations if schema file exists and CREATE TABLE not yet done
PGPASSWORD="${DB_PASS:-veckopeng_secret}" psql \
    -h "${DB_HOST:-postgres}" \
    -p "${DB_PORT:-5432}" \
    -U "${DB_USER:-veckopeng}" \
    -d "${DB_NAME:-veckopeng}" \
    -f /var/www/html/database/schema.sql \
    2>/dev/null || true

echo "Schema applied."

# Start PHP-FPM in background
php-fpm -D

# Start Nginx in foreground
exec nginx -g "daemon off;"
