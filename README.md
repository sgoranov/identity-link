# Identity Link

![License](https://img.shields.io/github/license/sgoranov/identity-link)
![Last Commit](https://img.shields.io/github/last-commit/sgoranov/identity-link)
![Issues](https://img.shields.io/github/issues/sgoranov/identity-link)
[![PHPUnit Tests](https://github.com/sgoranov/identity-link/actions/workflows/phpunit.yml/badge.svg)](https://github.com/sgoranov/identity-link/actions/workflows/phpunit.yml)
[![Security Audit](https://github.com/sgoranov/identity-link/actions/workflows/security-audit.yml/badge.svg)](https://github.com/sgoranov/identity-link/actions/workflows/security-audit.yml)


Identity Link is a modular, extensible OAuth2 authorization 
server built with Symfony and PHP. It provides a complete authentication and 
authorization solution designed for modern, distributed applications requiring 
token-based security.

This project leverages the powerful **league/oauth2-server** library under the hood, 
which provides a standards-compliant OAuth 2.0 server implementation in PHP. Identity 
Link integrates and extends it with features like JWT issuance, client and user 
management, 2FA support, and customizable UI themes.

## Purpose

The goal of Identity Link is to serve as a secure, flexible identity provider 
that can:

 - Act as an Authorization Server (issuing access and refresh tokens)
 - Handle User Authentication and Password Grants
 - Support Client Credentials, Authorization Code, and Refresh Token flows
 - Provide 2FA support for enhanced security
 - Expose a well-structured REST API for user, client, and group management

It is especially suited for integration into microservice-based systems or 
custom enterprise environments that require centralized user and token management.

## Components

- [DB Clients](https://github.com/sgoranov/identity-link-db-clients) - Manages registered OAuth2 clients, secrets, and their access policies
- [DB Users](https://github.com/sgoranov/identity-link-db-users) - Handles user registration, storage, and authentication
- [2FA](https://github.com/sgoranov/identity-link-2fa) - Provides optional two-factor authentication via TOTP
- [Shared](https://github.com/sgoranov/identity-link-shared) - Common utilities and abstractions shared across services

## Documentation

- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Running with Docker](docs/RUNNING_WITH_DOCKER.md)
- [OAuth Usage Guide](docs/OAUTH_USAGE_GUIDE.md)
- [Theme Customization](docs/THEME_CUSTOMIZATION.md)

## License

Identity Link is open source software licensed under the [MIT License](LICENSE), which permits reuse, modification, and distribution with minimal restrictions.
