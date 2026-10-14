# Tualo Office OAuth 2.0 / OpenID Connect

Authorization server package for Tualo Office. The package is based on the
Tualo package blueprint and uses League OAuth 2.0 Server plus its OpenID
Connect extension for protocol handling.

## Agreed first release

- Authorization Code grant with PKCE for user authorization
- Client Credentials grant for service-to-service access
- OpenID Connect ID tokens and user information for user sign-in
- Signed JWT access tokens and a public JWKS endpoint
- Tualo's existing user session for end-user authentication
- OAuth clients managed in a Tualo administration interface
- Private signing keys configured as PEM files outside the web root

The package will not implement OAuth/OIDC cryptography or grant processing
itself. Client secrets and signing keys must never be exposed in API responses,
logs, source control, or the administration UI after creation.

## Package layout

- `src/Routes/` contains thin protocol endpoint adapters.
- `src/Services/` contains authorization-server setup and Tualo integration.
- `src/Repositories/` contains OAuth entity persistence.
- `src/Entities/` contains OAuth/OIDC entities and claims.
- `src/Commands/` contains schema and package installation tasks.
- `src/js/` contains the Tualo administration interface.
- `docs/ARCHITECTURE.md` records the agreed protocol and integration boundaries.

## Development status

The package contains League-compatible OAuth entities and repositories, the
authorization-server factory, the stable OIDC subject mapping, and
session-database tables for clients, codes, and tokens. The authorization
entry point validates requests, requires PKCE with `S256`, binds requests to
the Tualo login session, and starts the separate consent flow. Refresh tokens
are issued only for `offline_access` and are rotated on use.

The schema is installed into Tualo's session database with `install-oauth2`.
Consent handling, the token/JWKS/UserInfo routes, and the client
administration interface are still required before the package can be used as
a running authorization server.

The authorization-server factory reads `issuer_url`, `auth_token_ttl`,
`access_token_ttl`, and `refresh_token_ttl` through `Setup::env()`. Durations
use PHP's ISO-8601 `DateInterval` format; defaults are `PT5M`, `PT15M`, and
`P30D`, respectively.

The authorization route also requires Tualo configuration values
`oauth2.private_key_path` and `oauth2.encryption_key`; the optional
`oauth2.private_key_passphrase` decrypts the PEM signing key. The signing key
must remain outside the document root.
