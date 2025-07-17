# Running the Application with Docker

**Table of Contents**

<!-- TOC -->
* [Running the Application with Docker](#running-the-application-with-docker)
  * [Docker Image](#docker-image)
  * [Environment Variables for Docker](#environment-variables-for-docker)
  * [Starting the App](#starting-the-app)
  * [Additional Docker Services](#additional-docker-services)
    * [Adminer – Database UI](#adminer--database-ui)
    * [MailHog – SMTP Test Server](#mailhog--smtp-test-server)
  * [Testing](#testing)
<!-- TOC -->

## Docker Image

The application is fully functional and can be run via Docker.
To get started, make sure you have Docker and Docker Compose installed.

## Environment Variables for Docker

**Important:** The Docker environment uses a separate environment file (e.g. .env.docker.default) that is not the 
same as the application’s main .env.

The Docker .env file is used only by Docker Compose and defines paths, credentials, and setup parameters needed to 
build and run all related containers.

Refer to the Docker Compose documentation for more on setting environment variables.

Below is a list of environment variables used in the Docker setup:

**Note:
DB_USER and DB_PASSWORD must match between the Docker environment file and the application’s .env configuration.
Otherwise, the app will not be able to connect to the database properly.**

| Variable                                | Description                                               | Example Value                                  |
|-----------------------------------------|-----------------------------------------------------------|------------------------------------------------|
| `DB_USER`                               | Database username (shared between app and services)       | `admin`                                        |
| `DB_PASSWORD`                           | Database password (shared between app and services)       | `admin`                                        |
| `IDENTITY_LINK_SOURCE_DIR`              | Path to the local clone of the main identity-link project | `/home/user/Projects/identity-link`            |
| `IDENTITY_LINK_DB_USERS_SOURCE_DIR`     | Path to the identity-link-db-users project                | `/home/user/Projects/identity-link-db-users`   |
| `IDENTITY_LINK_DB_CLIENTS_SOURCE_DIR`   | Path to the identity-link-db-clients project              | `/home/user/Projects/identity-link-db-clients` |
| `IDENTITY_LINK_2FA_SOURCE_DIR`          | Path to the identity-link-2fa project                     | `/home/user/Projects/identity-link-2fa`        |
| `IDENTITY_LINK_TEST_DATA_GENERATION`    | Enable automatic test data generation                     | `1`                                            |
| `IDENTITY_LINK_TEST_DATA_CLIENT_ID`     | Test client ID used for sample data                       | `client`                                       |
| `IDENTITY_LINK_TEST_DATA_CLIENT_SECRET` | Test client secret                                        | `client`                                       |
| `IDENTITY_LINK_TEST_DATA_REDIRECT_URI`  | Redirect URI for OAuth test client                        | `https://example.com/oauth/login/check`        |
| `IDENTITY_LINK_TEST_DATA_USER_NAME`     | Username for the seeded test user                         | `user`                                         |
| `IDENTITY_LINK_TEST_DATA_USER_PASS`     | Password hash for the test user (e.g., bcrypt)            | `4336b78adc9946baacb46a61630c2b45`             |
| `IDENTITY_LINK_TEST_DATA_GROUP_NAME`    | Name of the group assigned to the test user               | `administrator`                                |


## Starting the App

Run the following command to start all services using Docker Compose:

```bash
docker-compose --project-name identity-link \
-f identity-link/docker-compose.yml \
-f identity-link-2fa/docker-compose.yml \
-f identity-link-db-users/docker-compose.yml \
-f identity-link-db-clients/docker-compose.yml \
--env-file ~/.env up
```

**Replace ~/.env with the path to your actual Docker environment file if it's different.**

## Additional Docker Services

Your Docker setup also includes helpful tools for debugging, development, and manual inspection.

### Adminer – Database UI

Adminer is a lightweight, single-file database management tool that allows you to inspect and interact with your database from a web UI.

**Usage:**

Useful for inspecting tables, running queries, and debugging data issues manually.

**Access:**

Web interface: http://localhost:9005


### MailHog – SMTP Test Server

MailHog is used to capture outgoing emails during development, including things like password reset emails, without sending real emails.

**Usage:**

Captures all outgoing SMTP emails sent by the app.
View them via the web UI (no real email delivery occurs).

**Access:**

SMTP server: localhost:9025

Web interface: http://localhost:9026

### Redis Commander – Redis Web UI

Redis Commander is a lightweight web interface for browsing and managing your Redis data. It is useful for inspecting 
cache, sessions, rate limiter keys, and other Redis-stored values during development.

**Usage:**

Connects to your Redis instance and provides a visual interface to:
View, edit, delete, and search Redis keys
Inspect TTLs, hashes, lists, and other Redis data types
Debug Symfony cache, session, and rate limiter entries
No data is modified unless you explicitly make changes via the UI.

**Access:**

Web interface: http://localhost:9007

Port 9007 on your host forwards to Redis Commander’s default port 8081.

## Testing

You can execute all tests using the command bellow.

```bash
docker exec -it core bash -c "cd /var/www; php bin/phpunit"
```

