# Identity Link

![License](https://img.shields.io/github/license/sgoranov/identity-link)
![Last Commit](https://img.shields.io/github/last-commit/sgoranov/identity-link)
![Issues](https://img.shields.io/github/issues/sgoranov/identity-link)
[![PHPUnit Tests](https://github.com/sgoranov/identity-link/actions/workflows/phpunit.yml/badge.svg)](https://github.com/sgoranov/identity-link/actions/workflows/phpunit.yml)
[![Security Audit](https://github.com/sgoranov/identity-link/actions/workflows/vulnerability-scan.yml/badge.svg)](https://github.com/sgoranov/identity-link/actions/workflows/vulnerability-scan.yml)

Identity Link is a modular, extensible OAuth2 and OpenID Connect (OIDC) authorization
server built with Symfony and PHP. It provides a complete 
authentication and authorization solution designed for modern, 
distributed applications requiring token-based security.

![Application Architecture](./docs/diagram.svg)

## Why choose Identity Link

Identity Link is a modern identity management system built with 
scalability, flexibility, and security in mind. Key features include:

 - OAuth2 and OpenID Connect (OIDC) support
 - JWT access token issuance
 - Authorization Code, Client Credentials, and Password Grant flows
 - RESTful API for user, client, and group management
 - Modular architecture for pluggable components
 - Built-in TOTP two-factor authentication
 - Easy customization of UI, text, and translations
 - Horizontal scalability through microservices
 - Docker-based development environment
 - Extensive PHPUnit test coverage

## Components

- [DB Clients](https://github.com/sgoranov/identity-link-db-clients) – Manages registered OAuth2 clients, secrets, and their access policies
- [DB Users](https://github.com/sgoranov/identity-link-db-users) – Handles user registration, storage, and authentication
- [2FA](https://github.com/sgoranov/identity-link-2fa) – Provides optional two-factor authentication via TOTP
- [Console](https://github.com/sgoranov/identity-link-console) – Administrative UI for managing users, clients, groups, etc.
- [BFF](https://github.com/sgoranov/identity-link-bff) - Backend-for-frontend that handles OIDC login, stores the user session, and proxies requests to backend services while attaching the access token
- [Shared](https://github.com/sgoranov/identity-link-shared) – Common utilities and abstractions shared across services
- [Docker](https://github.com/sgoranov/identity-link-docker) – Centralized Docker Compose setup to orchestrate all services

## Documentation

- [API Contract Interfaces](docs/API_CONTRACTS.md)
- [Theme Customization](docs/THEME_CUSTOMIZATION.md)

## Deployment

For production deployments using Docker and Docker Compose, see the dedicated deployment repository:

- [Identity Link Docker deployment guide](https://github.com/sgoranov/identity-link-docker)

The Docker repository contains:
- Production Docker Compose configuration
- HTTPS certificate setup
- Secret generation
- Service provisioning
- Deployment and update instructions

## License

Identity Link is open source software licensed under the [MIT License](LICENSE), which permits reuse, 
modification, and distribution with minimal restrictions.
