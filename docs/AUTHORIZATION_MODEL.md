# Authorization Model

Identity Link supports two complementary ways to carry authorization information in access tokens: groups and OAuth
scopes. A deployment can use either model independently or combine them. Identity Link issues the resulting claims;
each protected resource remains responsible for validating the token and enforcing its own authorization rules.

## Groups

Groups provide a simple authorization model that works well when an application only needs a small number of broad
roles or memberships. Users and clients can rely on group assignments without being assigned or requesting OAuth
scopes.

Set the following environment variables to control the JWT `groups` claim:

```dotenv
JWT_INCLUDE_GROUPS_CLAIM=true
JWT_GROUPS_CLAIM_LIMIT=50
```

When group claims are enabled:

- tokens issued on behalf of a user contain that user's groups;
- client-credentials tokens contain the client's groups;
- only the configured maximum number of groups is included;
- disabling `JWT_INCLUDE_GROUPS_CLAIM` omits the claim entirely.

Example:

```json
{
  "groups": ["administrators", "support"]
}
```

Groups are application-defined. Identity Link does not prescribe how a protected resource maps group names to
permissions.

## Scopes and audiences

Scopes provide the standard OAuth model for describing what an access token permits. Identity Link represents the
granted scopes using the standard, space-delimited `scope` claim and returns the same value in the token endpoint
response.

```json
{
  "aud": "https://api.example.com",
  "scope": "users.read users.groups.read"
}
```

The main concepts are:

- **Audience** identifies the protected resource that may accept the token. It is emitted as the JWT `aud` claim.
- **Scope** describes an operation or level of access for that audience.
- **Alias** is a convenient request name that expands to multiple concrete scopes. Aliases are never emitted in the
  final token.
- **Client scope assignment** limits which scopes a client may receive for an audience.
- **User scope assignment** further limits user-based grants to scopes assigned to that user for the audience.

### How granted scopes are calculated

Identity Link begins with the scopes requested by the client, expands aliases, and keeps only scopes configured for
the client's audience. It then applies these restrictions:

1. scopes assigned to the client;
2. scopes assigned to the user for grants involving a user;
3. scopes granted by the authorization code when exchanging a code.

The token receives only the scopes that remain after every applicable restriction. Requesting no scopes grants no
scopes; Identity Link does not apply an implicit default scope set.

Groups do not participate in this calculation. Enabling groups does not add scopes, and a token with no scopes may
still contain groups.

## Configuring audiences, scopes, and aliases

Authorization configuration is loaded from YAML files in `config/authorization`. Files are processed in lexical
filename order and merged, so later files can add resources or refine earlier definitions. The built-in Identity Link
resource is defined in `10-identity-link.yaml`.

Additional files can declare new audiences and their scopes:

```yaml
resources:
  'https://orders.example.com':
    scopes:
      orders.read:
        description: View orders
      orders.write:
        description: Create and update orders

    aliases:
      orders.manage:
        description: Manage orders
        scopes:
          - orders.read
          - orders.write
```

An alias may reference only concrete scopes defined for the same audience. Scope and alias metadata can be discovered
through the authenticated authorization metadata endpoints:

- `GET /api/v1/authorization/audiences`
- `GET /api/v1/authorization/scopes?audience=https://orders.example.com`

Scope assignments themselves are provided by the configured user and client connectors. This allows the scope catalog
to remain local to Identity Link while assignments are managed by the corresponding user and client services.

## Fixed audience per client

Identity Link does not currently implement the OAuth `resource` request parameter from
[RFC 8707](https://datatracker.ietf.org/doc/html/rfc8707). Instead, every client registration has exactly one audience.
That audience determines:

- which configured scope catalog is used;
- which client and user scope assignments are queried;
- the value of the access token's `aud` claim.

A client therefore cannot select a different audience or request tokens for multiple resources at authorization or
token request time. Register separate clients when an application needs tokens for different protected resources.

This fixed model is simpler than RFC 8707 and prevents a client from redirecting a token to a resource other than the
one configured in its registration, but it is less flexible for clients that call several independent APIs.

## Choosing a model

Use groups when broad roles or memberships are sufficient and you want minimal OAuth-specific configuration. Use
scopes when APIs need explicit, audience-specific permissions and standardized OAuth interoperability. Use both when
groups describe organizational membership while scopes limit the operations granted to a particular token.

Regardless of the model, protected resources should validate the token issuer, audience, signature, and expiry before
enforcing `scope` or `groups` values.
