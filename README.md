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

## Why Choose Identity Link

Identity Link is a modern identity management system built with 
scalability, flexibility, and security in mind. Here’s why it stands out:

### Microservice-Based Architecture

Built as a set of microservices, Identity Link allows horizontal 
scaling-spin up more instances of the same service to handle 
increased load and maintain high performance.

### Modular Design

Identity Link is composed of swappable modules, making it easy 
to adapt to your infrastructure or requirements:

 - The default `db-user` module stores user data in PostgreSQL.
 - You can easily replace it with a custom implementation that pulls users from Active Directory, an API, or any other system.

### Two-Factor Authentication Support

Security is built in. TOTP (e.g., Google Authenticator) is supported 
out of the box. Thanks to the modular design, you can also implement 
other 2FA methods like SMS verification or third-party services 
with minimal changes.

### Fully Customizable UI and Text

Identity Link is fully customizable. You can:

 - Apply your own themes to modify the look and feel.
 - Customize all texts and labels.
 - Provide translations for a multilingual user experience.


## Features

Identity Link provides a secure and flexible identity solution for modern applications.
Key features include:

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

- [DB Clients](https://github.com/sgoranov/identity-link-db-clients) - Manages registered OAuth2 clients, secrets, and their access policies
- [DB Users](https://github.com/sgoranov/identity-link-db-users) - Handles user registration, storage, and authentication
- [2FA](https://github.com/sgoranov/identity-link-2fa) - Provides optional two-factor authentication via TOTP
- [Shared](https://github.com/sgoranov/identity-link-shared) - Common utilities and abstractions shared across services
- [Docker](https://github.com/sgoranov/identity-link-docker) – Centralized Docker Compose setup to orchestrate all services locally for development or testing

## Documentation

- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [OAuth Usage Guide](docs/OAUTH_USAGE_GUIDE.md)
- [OpenID Connect Usage Guide](docs/OIDC_USAGE_GUIDE.md)
- [API Contract Interfaces](docs/API_CONTRACTS.md)
- [Theme Customization](docs/THEME_CUSTOMIZATION.md)

## License

Identity Link is open source software licensed under the [MIT License](LICENSE), which permits reuse, 
modification, and distribution with minimal restrictions.
