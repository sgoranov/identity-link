# API Contract Interfaces

This document describes the key interfaces that connect the core Identity Link 
service with custom implementations for user, client, and 2FA management.

These interfaces serve as contracts between the core OAuth2/OIDC authorization 
server and your custom service layers, enabling flexible integration.


## ClientConnectorInterface

Manages OAuth2 client retrieval and group membership queries.

- `getClientByClientCredentials($clientIdentifier, $clientSecret, $grantType): ?ClientResponseInterface`  
  Retrieve a client by its credentials and grant type.

- `getClientById(string $id): ?ClientResponseInterface`  
  Retrieve a client by its identifier.

- `getGroups(string $uuid, int $limit): GroupsResponseInterface`  
  Retrieve groups associated with a client or user by UUID.


## ClientResponseInterface

Represents client data returned by the `ClientConnectorInterface`.

- `getId(): string` - Returns client ID
- `getName(): string` - Returns client name
- `getRedirectUri(): array|string` - Returns a redirect URI or a list of allowed redirect URIs for the client
- `isPublic(): bool` - Whether the client is public or confidential
- `getScopes(): array` - List of scopes the client is allowed
- `getGrantTypes(): array` - List of allowed grant types
- `isConsentRequired(): bool` - Whether the OAuth client requires user consent to be granted via the consent screen during the authorization flow
- `getApplicationUrl(): ?string` - Returns the application's home page URL. This URL is intended for informational purposes, such as displaying application details to users or administrators, and is not used for redirect URI validation.
- `getTermsOfServiceUrl(): ?string` - Returns the URL of the application's Terms of Service, if available. This may be presented on the consent screen or in client metadata.
- `getPrivacyPolicyUrl(): ?string` - Returns the URL of the application's Privacy Policy, if available. This may be presented on the consent screen or in client metadata to inform users how their data is handled.
- `getLogoUrl(): ?string` - Returns the URL of the application's logo, if available. The authorization server may use this to visually identify the client on consent screens or in administrative interfaces.

## GroupsResponseInterface

Holds a list of groups associated with a client or user.

- `getGroups(): array` - Returns array of groups

## TwoFaConnectorInterface

Handles two-factor authentication (2FA) operations.

- `initiateAuthenticationRequest(string $userIdentifier, string $redirectUri): ?string`  
  Starts a 2FA request for a user, returning an ID or token.

- `validateAuthenticationRequest(string $id): ?TwoFaConnectorResponseInterface`  
  Validates the 2FA request identified by the given ID.

## TwoFaConnectorResponseInterface

Represents the response from a 2FA request.

- `getUserId(): string`
  Returns the user ID associated with the request.

## UserConnectorInterface

Manages user retrieval and authentication.

- `getUserByUserCredentials($username, $password, $grantType, ClientEntityInterface $clientEntity): ?UserResponseInterface`  
  Authenticates a user by credentials and client context.

- `getUserById(string $id): ?UserResponseInterface`  
  Retrieve a user by their unique ID.

- `getGroups(string $id, int $limit): GroupsResponseInterface`  
  Retrieve groups associated with a user.

## UserResponseInterface

Represents user data returned by `UserConnectorInterface`.

- `getId(): string` - Returns user ID
- `getClaims(): array` - Returns user claims for inclusion in tokens or userinfo response
- `twoFaEnabled(): bool` - Whether this user must complete two-factor authentication during login when 2FA is globally enabled

## Summary

Implement these interfaces in your custom services to integrate with Identity Link's core authorization flows, 
ensuring flexible and secure user and client management.
