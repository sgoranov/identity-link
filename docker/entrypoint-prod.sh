#!/usr/bin/env bash
set -e

cd /app

# Fail the deployment on an invalid authorization configuration instead of the first request.
php bin/console identity-link:validate-authorization

php bin/console importmap:install
php bin/console asset-map:compile

php bin/console doctrine:migrations:migrate \
    --no-interaction \
    --env=prod

exec "$@"