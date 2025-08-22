# OAuth2 Usage Guide

<!-- TOC -->
* [OAuth2 Usage Guide](#oauth2-usage-guide)
  * [Authorization Code Flow](#authorization-code-flow)
    * [Step 1: Request Authorization Code](#step-1-request-authorization-code)
    * [Step 2: Exchange Authorization Code for Tokens](#step-2-exchange-authorization-code-for-tokens)
  * [Resource Owner Password Credentials Flow](#resource-owner-password-credentials-flow)
  * [Client Credentials Flow](#client-credentials-flow)
  * [Refresh Token Flow](#refresh-token-flow)
  * [Token Revocation (RFC 7009)](#token-revocation-rfc-7009)
  * [Token Introspection (RFC 7662)](#token-introspection-rfc-7662)
  * [Important Notes](#important-notes)
<!-- TOC -->

This document describes how to interact with the OAuth2 server using various OAuth2 grant flows.
It includes example commands for obtaining access tokens and using them for authentication.

## Authorization Code Flow

This flow is typically used by web applications where the user logs in via a browser.

### Step 1: Request Authorization Code

Open the following URL in a browser (replace client and redirect_uri with your client ID and callback URL):

```bash
https://auth.example.com/oauth2/auth?response_type=code&client_id=client&redirect_uri=http://example.com&state=mystate&scope=groups
```

The user will be prompted to log in. After successful authentication, the server will redirect to 
your redirect_uri with an authorization code in the URL query parameter.

### Step 2: Exchange Authorization Code for Tokens

Use the authorization code returned in Step 1 (replace XXXXXXXXXXX with that code) to 
obtain access and refresh tokens:

```bash
curl -k -u client:client https://auth.example.com/oauth2/token -d 'grant_type=authorization_code&code=XXXXXXXXXXX&redirect_uri=http://example.com'
```

## Resource Owner Password Credentials Flow

This flow is typically used for trusted applications where the user provides their username 
and password directly.

```bash
curl -k -u client:client https://auth.example.com/oauth2/token -d 'grant_type=password&username=USERNAME&password=PASSWORD&scope=groups'
```

Replace USERNAME and PASSWORD with the actual user credentials. This will return an access token and refresh token.

## Client Credentials Flow

This flow is used for machine-to-machine authentication, where no user interaction is required.

```bash
curl -k -u client:client https://auth.example.com/oauth2/token -d 'grant_type=client_credentials&scope=groups'
```

This will return an access token for the client itself, without user context.

## Refresh Token Flow

When your access token expires, you can use the refresh token to get a new access token 
without re-authenticating the user.

```bash
curl -k -u client:client https://auth.example.com/oauth2/token -d 'grant_type=refresh_token&refresh_token=XXXXXXXXXXX'
```

Replace XXXXXXXXXXX with the refresh token you received earlier.


## Token Revocation (RFC 7009)

The revocation endpoint allows a client to invalidate an access token or refresh token.
Both valid and invalid tokens must result in an HTTP 200 response, but invalid clients will receive 401 Unauthorized.

**Request**

```bash
curl -X POST https://auth.example.com/oauth2/token/revoke \
  -u client:client \
  -d 'token=XXXXXXXXXXX'
```

**Response**

```text
HTTP/1.1 200 OK
```

## Token Introspection (RFC 7662)

The introspection endpoint allows a client to check the validity and metadata of an access or refresh token.

**Request**

```bash
curl -X POST https://auth.example.com/oauth2/token/introspect \
  -u client:client \
  -d 'token=XXXXXXXXXXX'
```

**Response (active token)**

```json
{
  "active": true,
  "client_id": "client",
  "scope": "openid profile email",
  "sub": "user-123",
  "aud": "my-api",
  "iss": "https://auth.example.com",
  "exp": 1755692010,
  "iat": 1755688410
}
```

**Response (inactive or expired token)**

```json
{
  "active": false
}
```

## Important Notes

 - Always replace placeholders like client, redirect_uri, USERNAME, PASSWORD, and 
XXXXXXXXXXX with the actual values relevant to your setup.
 - The client and client:client in curl commands refer to the client ID and 
client secret respectively.
 - The -k flag is used here to skip SSL verification; in production environments, 
ensure your SSL certificates are properly configured and trusted.

