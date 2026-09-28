#!/bin/sh
set -e

if [ ! -d vendor ]; then
  echo "Installing PHP dependencies..."
  composer install --no-interaction --prefer-dist
fi

echo "Waiting for the database..."
until php bin/console dbal:run-sql "SELECT 1" >/dev/null 2>&1; do
  sleep 1
done

php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction

if [ ! -f config/jwt/private.pem ]; then
  echo "Generating JWT keypair..."
  php bin/console lexik:jwt:generate-keypair --skip-if-exists
fi

exec "$@"
