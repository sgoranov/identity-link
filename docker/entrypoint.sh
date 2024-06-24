#!/usr/bin/env bash

# Modify /etc/hosts
DEFAULT_ROUTE=$(ip route show default | awk '/default/ {print $3}')
echo "$DEFAULT_ROUTE localhost.container.com" >> /etc/hosts

cd /var/www/

# Run composer install and replace the correct configuration
# rm -rf vendor
composer install --no-scripts

# Database setup
until psql -c "\q"; do sleep 3; done
echo "SELECT 'CREATE DATABASE \"php-identity-link\"' WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = '\"php-identity-link\"')\gexec" \
 | psql -v ON_ERROR_STOP=1
php bin/console -e dev doctrine:migrations:migrate --no-interaction

echo "SELECT 'CREATE DATABASE \"php-identity-link-test\"' WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = '\"php-identity-link-test\"')\gexec" \
 | psql -v ON_ERROR_STOP=1
php bin/console -e test doctrine:migrations:migrate --no-interaction
php bin/console -e test -n doctrine:fixtures:load

chmod 600 tests/resources/private.key

# Set correct permissions on var/
rm -rf var/cache/*
chmod -R o+rw var/

# This will exec the CMD from Dockerfile
exec "$@"
