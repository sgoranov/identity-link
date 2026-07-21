#!/usr/bin/env bash
set -e

cd /app

composer install --no-interaction --no-progress

php bin/console doctrine:database:create --if-not-exists --no-interaction --env=dev
php bin/console doctrine:migrations:migrate --no-interaction --env=dev

# This will exec the CMD from Dockerfile
exec "$@"