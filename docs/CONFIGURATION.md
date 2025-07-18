# Configuration Parameters

**Table of Contents**

<!-- TOC -->
* [Configuration Parameters](#configuration-parameters)
  * [Database Configuration](#database-configuration)
  * [Application Settings](#application-settings)
  * [DbUserConnector](#dbuserconnector)
  * [DbClientConnector](#dbclientconnector)
  * [TwoFaConnector](#twofaconnector)
<!-- TOC -->

This document lists the environment variables used to configure the application. These variables control database
connections, authentication settings, and API endpoints for the various microservice connectors integrated into the system.

The default values are set in the `.env` file. For environment-specific overrides, such as development, staging, or production, you can create a `.env.local` file where these defaults can be safely overridden without modifying the main `.env`.

## Database Configuration

Contains parameters for connecting to the application’s database. Adjust these values to match your database
credentials and host settings.

| Variable        | Description           | Example Value        |
|-----------------|-----------------------|----------------------|
| **DB_USER**     | Database username     | ChangeMe             |
| **DB_PASSWORD** | Database password     | ChangeMe             |
| **DB_HOST**     | Database host address | host.docker.internal |
| **DB_PORT**     | Database port number  | 9006                 |

## Application Settings

General application configuration, including authentication type and JWT token options.

| Variable                     | Description                                                                     | Example Value                              |
|------------------------------|---------------------------------------------------------------------------------|--------------------------------------------|
| **AUTHENTICATOR_TYPE**       | Type of authenticator to use (currently only "PasswordAuthenticator" supported) | PasswordAuthenticator                      |
| **JWT_INCLUDE_GROUPS_CLAIM** | Whether to include the "groups" claim in generated JWT tokens                   | true                                       |
| **JWT_GROUPS_CLAIM_LIMIT**   | Maximum number of groups to include in the JWT "groups" claim (if enabled)      | 50                                         |
| **REDIS_DSN**                | Redis server connection string (used only when APP_ENV=distributed)             | redis://host.docker.internal:9008          |
| **OAUTH2_ALLOWED_SCOPES**    | Comma-separated list of allowed OAuth2 scopes                                   | openid,profile,email,offline_access,groups |


## DbUserConnector

API endpoints for the external user management microservice.
Used by the DbUserConnector implementation of UserConnectorInterface to authenticate, query, and fetch user data.
GitHub repo: https://github.com/sgoranov/identity-link-db-users

| Variable                | Description                                                                | Example Value                                     |
|-------------------------|----------------------------------------------------------------------------|---------------------------------------------------|
| **USER_QUERY_ENDPOINT** | Endpoint to query users (search/filter)                                    | http://host.docker.internal:9001/api/v1/query     |
| **USER_AUTH_ENDPOINT**  | Endpoint for user authentication                                           | http://host.docker.internal:9001/api/v1/auth      |
| **USER_FETCH_ENDPOINT** | Endpoint to fetch user details by ID (`{id}` replaced with actual user ID) | http://host.docker.internal:9001/api/v1/user/{id} |
| **RESET_PASSWORD_URL**  | URL for reset password link; if empty, reset password option is disabled   | http://host.docker.internal:9001/reset-password   |


## DbClientConnector

API endpoints for the external client management microservice.
Used by the DbClientConnector implementation of ClientConnectorInterface to authenticate and query client data.
GitHub repo: https://github.com/sgoranov/identity-link-db-clients

| Variable                  | Description                               | Example Value                                 |
|---------------------------|-------------------------------------------|-----------------------------------------------|
| **CLIENT_AUTH_ENDPOINT**  | Endpoint for client authentication        | http://host.docker.internal:9002/api/v1/auth  |
| **CLIENT_QUERY_ENDPOINT** | Endpoint to query clients (search/filter) | http://host.docker.internal:9002/api/v1/query |


## TwoFaConnector

API endpoints for the two-factor authentication (2FA) service.
Used by the TwoFaConnector implementation of TwoFaConnectorInterface to perform authentication and verification.
GitHub repo: https://github.com/sgoranov/identity-link-2fa

| Variable                  | Description                                                                   | Example Value                                |
|---------------------------|-------------------------------------------------------------------------------|----------------------------------------------|
| **TWO_FA_AUTH_ENDPOINT**  | Endpoint for 2FA authentication. Leave empty to disable 2FA                   | http://host.docker.internal:9003/api/v1/auth |
| **TWO_FA_INDEX_ENDPOINT** | Endpoint to verify 2FA token by user ID (`{id}` replaced with actual user ID) | http://localhost:9003/2fa/verify/{id}        |

