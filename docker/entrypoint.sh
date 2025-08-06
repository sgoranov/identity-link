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
echo "SELECT 'CREATE DATABASE \"identity-link\"' WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = '\"identity-link\"')\gexec" \
 | psql -v ON_ERROR_STOP=1
php bin/console -e dev doctrine:migrations:migrate --no-interaction

# PHPUnit setup
echo "SELECT 'CREATE DATABASE \"test-identity-link\"' WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = '\"test-identity-link\"')\gexec" \
 | psql -v ON_ERROR_STOP=1
php bin/console -e test doctrine:migrations:migrate --no-interaction
php bin/console -e test -n doctrine:fixtures:load

chmod 600 tests/resources/private.key

# Set correct permissions on var/
rm -rf var/cache/*
php bin/console identity-link:generate-keys
chmod 600 var/private.key
chown -R www-data:www-data var

# This will exec the CMD from Dockerfile
exec "$@" &

APACHE_PID=$!

# Test data generation
if [ "${TEST_DATA_GENERATION:-0}" -eq 1 ]; then
  AUTH_TOKEN=$(php bin/console identity-link:generate-jwt)
  RETRY_INTERVAL=3

  CLIENT_API_URL="http://host.docker.internal:9002/api/v1"
  while true; do
    response=$(curl -s -o /dev/null -w "%{http_code}" "$CLIENT_API_URL/ping")

    if [ "$response" -eq 200 ]; then
      response=$(curl -s -w "%{http_code}" --location "$CLIENT_API_URL/group" \
          --header "Content-Type: application/json" \
          --header "Authorization: Bearer $AUTH_TOKEN" \
          --data "{\"name\": \"$TEST_DATA_GROUP_NAME\"}")

      http_status="${response: -3}"
      if [ "$http_status" -ne 201 ]; then
          echo "Failed to create clients group. HTTP Status: $http_status"
          exit 1
      fi

      json_body="${response::-3}"
      GROUP_ID=$(echo "$json_body" | jq -r '.response.group.id')

      response=$(curl -s -w "%{http_code}" --location "$CLIENT_API_URL/client" \
          --header "Content-Type: application/json" \
          --header "Authorization: Bearer $AUTH_TOKEN" \
          --data "{
              \"name\": \"$TEST_DATA_CLIENT_ID\",
              \"description\": \"description\",
              \"redirectUri\": \"$TEST_DATA_REDIRECT_URI\",
              \"grantTypes\": [\"client_credentials\", \"authorization_code\", \"password\", \"refresh_token\"],
              \"groups\": [\"$GROUP_ID\"],
              \"isPublic\": false
          }")

      http_status="${response: -3}"
      if [ "$http_status" -ne 201 ]; then
          echo "Failed to create client. HTTP Status: $http_status"
          exit 1
      fi

      json_body="${response::-3}"
      CLIENT_ID=$(echo "$json_body" | jq -r '.response.client.id')
      EXPIRATION_DATE=$(date -d "+1 month" +"%Y-%m-%dT%H:%M:%S")

      response=$(curl -s -w "%{http_code}" --location "$CLIENT_API_URL/secret" \
          --header "Content-Type: application/json" \
          --header "Authorization: Bearer $AUTH_TOKEN" \
          --data "{
              \"password\": \"$TEST_DATA_CLIENT_SECRET\",
              \"passwordHint\": \"pass hint\",
              \"expirationDateTime\": \"$EXPIRATION_DATE\",
              \"client\": \"$CLIENT_ID\"
          }")

      http_status="${response: -3}"
      if [ "$http_status" -ne 201 ]; then
          echo "Failed to create client secret. HTTP Status: $http_status"
          exit 1
      fi

      echo "Client created successfully."
      break;
    fi

    echo "Client API is not up yet (HTTP status: $response). Retrying in $RETRY_INTERVAL seconds..."
    sleep $RETRY_INTERVAL
  done

  USER_API_URL="http://host.docker.internal:9001/api/v1"
  while true; do
    response=$(curl -s -o /dev/null -w "%{http_code}" "$USER_API_URL/ping")

    if [ "$response" -eq 200 ]; then
      response=$(curl -s -w "%{http_code}" --location "$USER_API_URL/group" \
          --header "Content-Type: application/json" \
          --header "Authorization: Bearer $AUTH_TOKEN" \
          --data "{\"name\": \"$TEST_DATA_GROUP_NAME\"}")

      http_status="${response: -3}"
      if [ "$http_status" -ne 201 ]; then
          echo "Failed to create users group. HTTP Status: $http_status"
          exit 1
      fi

      json_body="${response::-3}"
      GROUP_ID=$(echo "$json_body" | jq -r '.response.group.id')

      response=$(curl -s -w "%{http_code}" --location "$USER_API_URL/user" \
          --header "Content-Type: application/json" \
          --header "Authorization: Bearer $AUTH_TOKEN" \
          --data "{
              \"username\": \"$TEST_DATA_USER_NAME\",
              \"password\": \"$TEST_DATA_USER_PASS\",
              \"firstName\": \"Firstname\",
              \"lastName\": \"Lastname\",
              \"email\": \"test@phpidentitylink.com\",
              \"grantTypes\": [\"password\", \"authorization_code\", \"refresh_token\"],
              \"groups\": [\"$GROUP_ID\"]
          }")

      http_status="${response: -3}"
      if [ "$http_status" -ne 201 ]; then
          echo "Failed to create user. HTTP Status: $http_status"
          exit 1
      fi

      echo "User created successfully."
      break;
    fi

    echo "User API is not up yet (HTTP status: $response). Retrying in $RETRY_INTERVAL seconds..."
    sleep $RETRY_INTERVAL
  done
fi

wait $APACHE_PID