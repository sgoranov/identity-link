# Identity Link

Identity Link is an OAuth2 server implemented using Symfony and PHP. 
The project is designed with a microservices architecture, where each repository represents a 
distinct service. The communication is unidirectional from the core service to the other 
components, which do not interact directly with each other. Access to the components is 
secured using JWT tokens issued by the core service. Here’s an overview of the main components:

## Components

 - Core: The main service responsible for issuing and validating tokens.
 - [DB Clients](https://github.com/sgoranov/identity-link-db-clients): Manages the client entities that can request tokens.
 - [DB Users](https://github.com/sgoranov/identity-link-db-users): Manages the user entities and their authentication.
 - [2FA](https://github.com/sgoranov/identity-link-2fa): Provides two-factor authentication support using TOTP.
 - [Shared](https://github.com/sgoranov/identity-link-shared): Contains shared libraries and utilities used across the other components.

## Architecture

Core issues and validates JWT tokens.
DB Clients and DB Users handle client and user data respectively, accessible only via valid tokens from the core.
2FA is optional and is used  for two-factor authentication, enhancing security.
Shared libraries ensure reusable and maintainable code across services.

## Example Workflow

User Authentication: The user authenticates through the core service, receiving a JWT token.
Client/User Management: The user can manage clients and users via the DB Clients and DB Users services using the token.
Two-Factor Authentication: For enhanced security, the user sets up two-factor authentication through the 2FA service.

## Docker image

The application is fully functional and available as a Docker image. To start using it you will have to install [Docker](https://www.docker.com/) and
[Docker compose](https://docs.docker.com/compose/).

The Docker setup relies on [few environment variables](.env.docker.default) used for configuration. Please review
and define these as needed. Refer to [docker compose documentation](https://docs.docker.com/compose/environment-variables/set-environment-variables/)
for more information about environment variables.

Start with docker compose:
```bash
docker-compose --project-name identity-link \
 -f identity-link/docker-compose.yml \ 
 -f identity-link-2fa/docker-compose.yml \ 
 -f identity-link-db-users/docker-compose.yml \
 -f identity-link-db-clients/docker-compose.yml \
 --env-file ~/.env up
```

## Testing

You can execute all tests using the command bellow.

```bash
docker exec -it core bash -c "cd /var/www; php bin/phpunit"
```
