# OpenID Connect Usage Guide

<!-- TOC -->
* [OpenID Connect Usage Guide](#openid-connect-usage-guide)
  * [Authorization Code Flow (with ID Token)](#authorization-code-flow-with-id-token)
    * [Step 1: Request Authorization Code](#step-1-request-authorization-code)
    * [Step 2: Exchange Authorization Code for ID and Access Tokens](#step-2-exchange-authorization-code-for-id-and-access-tokens)
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