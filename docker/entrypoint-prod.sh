#!/usr/bin/env bash
set -e

cd /app

php bin/console importmap:install
php bin/console asset-map:compile

php bin/console doctrine:migrations:migrate \
    --no-interaction \
    --env=prod

exec "$@"