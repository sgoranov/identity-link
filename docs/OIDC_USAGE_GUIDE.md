# OpenID Connect Usage Guide

<!-- TOC -->
* [OpenID Connect Usage Guide](#openid-connect-usage-guide)
  * [Authorization Code Flow (with ID Token)](#authorization-code-flow-with-id-token)
    * [Step 1: Request Authorization Code](#step-1-request-authorization-code)
    * [Step 2: Exchange Authorization Code for ID and Access Tokens](#step-2-exchange-authorization-code-for-id-and-access-tokens)
  * [OpenID Connect Endpoints](#openid-connect-endpoints)
    * [Discovery Endpoint](#discovery-endpoint)
    * [JWKS Endpoint](#jwks-endpoint)
    * [UserInfo Endpoint](#userinfo-endpoint)
<!-- TOC -->

This document describes how to interact with the OpenID Connect (OIDC) server built on top of OAuth2.
OIDC adds a standardized ID token and user identity layer to OAuth2 flows.

## Authorization Code Flow (with ID Token)
This is the most common flow used by web and mobile apps. It authenticates the user and returns both an access token and an ID token.

### Step 1: Request Authorization Code

Open the following URL in a browser (replace the parameters appropriately):

```bash
https://auth.example.com/oauth2/auth?response_type=code&client_id=client&redirect_uri=http://example.com&scope=openid profile email&state=xyz
```

At a minimum, include openid in the scope to enable OpenID Connect.

After the user logs in and consents, you’ll receive an authorization code via redirect.

### Step 2: Exchange Authorization Code for ID and Access Tokens

Use the authorization code returned in Step 1 (replace XXXXXXXXXXX with that code) to
obtain access, refresh tokens and id token:


```bash
curl -k -u client:client https://auth.example.com/oauth2/token \
  -d 'grant_type=authorization_code&code=XXXXXXXXXXX&redirect_uri=http://example.com'
```

Response:

```json
{
  "access_token": "eyJ...abc",
  "refresh_token": "def...",
  "id_token": "eyJ...xyz",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

## OpenID Connect Endpoints

### Discovery Endpoint

The discovery endpoint provides metadata about the OIDC server’s configuration, including supported 
endpoints, scopes, claims, and algorithms.

```bash
curl https://auth.example.com/.well-known/openid-configuration
```

Sample response snippet:

```json
{
  "issuer": "https://auth.example.com",
  "authorization_endpoint": "https://auth.example.com/oauth2/auth",
  "token_endpoint": "https://auth.example.com/oauth2/token",
  "userinfo_endpoint": "https://auth.example.com/user-info",
  "jwks_uri": "https://auth.example.com/jwks",
  "response_types_supported": [
    "code"
  ],
  "subject_types_supported": [
    "public"
  ],
  "id_token_signing_alg_values_supported": [
    "RS256"
  ],
  "code_challenge_methods_supported": [
    "S256"
  ]
}
```

### JWKS Endpoint

The JSON Web Key Set (JWKS) endpoint exposes the public keys used by the OIDC server to sign 
ID tokens and access tokens. Clients use this to verify token signatures.

Retrieve the keys with:

```bash
curl https://auth.example.com/jwks
```

The response contains a JSON object with an array of keys, for example:

```json
{
  "keys": [
    {
      "kty": "RSA",
      "use": "sig",
      "alg": "RS256",
      "kid": "5eda9b41-085c-403f-85e7-50d3b4131082",
      "n": "nTzb4ls0L4MGmtzMATRBiKcyy1Iqcxyk8mAbw7JOHmIIt8kAzZnw3rJhowYukQK6nqVMaJEhfxR67s2sFMK_rlezRagH5c_uZ8OuvDAS4lNOLbxwZwbtL7fyFu8RdBAF8DJ0X5AEo6dPYg4Vl8uGtw_hPbOArKE7rjPOhqY05Wzq3ZUmj_G5jjh4Kmqz4rTi5jFIeOpS8scTrbYew8wUKWxth1ve43qUtWpLKw_wffpG61LBspydwOy7D8M2p3rZrV9cFvypaMgBcIgn0y5ImoXEVhOx7sT5yFj5WpWbcMqBEGeHMmon7BQpUDMDhrFpyhJE1FX-hrvmQiDDMOi0ew",
      "e": "AQAB"
    }
  ]
}
```

### UserInfo Endpoint

The UserInfo endpoint returns claims about the authenticated user. It requires a valid access token.

Call it with an access token as a Bearer token:

```bash
curl -H "Authorization: Bearer {{access_token}}" https://auth.example.com/user-info
```

Sample response (claims returned depend on requested scopes):

```json
{
  "sub": "248289761001",
  "name": "Jane Doe",
  "email": "janedoe@example.com",
  "preferred_username": "j.doe"
}
```
