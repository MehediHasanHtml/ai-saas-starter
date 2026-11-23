#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
  cp .env.example .env
fi

if ! grep -q "^APP_KEY=base64" .env; then
  php artisan key:generate --force
fi

# Only the API container runs migrations (RUN_MIGRATIONS=true).
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  echo "Waiting for PostgreSQL..."
  until pg_isready -h "${DB_HOST:-postgres}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-postgres}" >/dev/null 2>&1; do
    sleep 1
  done

  php artisan migrate --force

  if [ "${SEED_DEMO_DATA:-false}" = "true" ]; then
    php artisan db:seed --force
  fi
fi

exec "$@"
