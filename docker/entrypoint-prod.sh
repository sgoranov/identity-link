#!/usr/bin/env bash
set -e

cd /app

php bin/console doctrine:migrations:migrate \
    --no-interaction \
    --env=prod

exec "$@"