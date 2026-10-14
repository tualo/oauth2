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
OAuth repositories and environment settings use that same session database
(`Session::$db`), not the tenant database returned by `Session::getDB()`.
Consent handling, the token/JWKS/UserInfo routes, and the client
administration interface are still required before the package can be used as
a running authorization server.

The authorization-server factory reads `issuer_url`, `auth_token_ttl`,
`access_token_ttl`, and `refresh_token_ttl` through `Setup::env()`. Durations
use PHP's ISO-8601 `DateInterval` format; defaults are `PT5M`, `PT15M`, and
`P30D`, respectively.

### Configure the signing key

`oauth2.private_key_path` must be an **absolute filesystem path** to a readable
PEM-encoded RSA private key on the machine/container running PHP. It is not a
URL and must not include the `file://` prefix. Store it outside the web
document root and outside the repository; the OAuth server adds the `file://`
prefix itself when loading the key. For example, if the document root is
`/var/www/tualo/server`, use a path such as
`/etc/tualo/oauth2/private.key`, not a path below `/var/www/tualo/server`.

Create a key on the server, restrict access, and grant read permission to the
PHP/web-server user:

```sh
sudo install -d -o root -g www-data -m 0750 /etc/tualo/oauth2
sudo openssl genpkey -algorithm RSA \
  -pkeyopt rsa_keygen_bits:4096 \
  -out /etc/tualo/oauth2/private.key
sudo chown root:www-data /etc/tualo/oauth2/private.key
sudo chmod 0640 /etc/tualo/oauth2/private.key
sudo -u www-data openssl pkey \
  -in /etc/tualo/oauth2/private.key -check -noout
```

Replace `www-data` with the actual group and user used by PHP-FPM/the web
server. The final validation command should succeed when run as that user.
Keep a protected backup of the key: replacing it invalidates signatures from
the old key unless key rotation is configured.

Generate a new Defuse encryption key using the application's Composer
autoload (run this from the application directory that contains `vendor/`):

```sh
php -r 'require "vendor/autoload.php"; echo \Defuse\Crypto\Key::createNewRandomKey()->saveToAsciiSafeString(), PHP_EOL;'
```

Copy the generated value into the application's `.htconfig` under the
`[oauth2]` section, along with the private key path:

```ini
[oauth2]
private_key_path=/etc/tualo/oauth2/private.key
encryption_key=PASTE_THE_GENERATED_VALUE_HERE
```

The text above is an instruction, not a usable encryption key: do not leave
`PASTE_THE_GENERATED_VALUE_HERE` or `DEFUSE_ASCII_SAFE_KEY` in the configuration.
Keep the generated key secret and backed up securely; losing or changing it
invalidates encrypted authorization data such as pending codes and tokens.

If the PEM private key is **not encrypted**, omit
`private_key_passphrase` entirely. In `.htconfig`, `null` is read as the
literal string `"null"` and will be treated as an actual passphrase. If the PEM
is encrypted, set `private_key_passphrase` to its real passphrase. Neither the
encryption key nor the passphrase belongs in source control or in a publicly
served configuration file. A missing, unreadable, invalid, or web-root-resident
private key prevents the authorization server from starting.

## Testing the authorization login

Use the example script with an existing client whose redirect URI is registered.
It creates a PKCE S256 request and opens it in the default browser so the Tualo
login session and notification-based continuation are tested in that browser:

```sh
OAUTH2_ISSUER_URL="https://auth.example.test" \
OAUTH2_CLIENT_ID="your-client-id" \
OAUTH2_REDIRECT_URI="https://client.example/callback" \
./tools/authorize-example.sh
```

The script requires `openssl` and `php`. Set `OPEN_BROWSER=0` to print the
URL without opening a browser. The code verifier is printed for a later token
exchange. At this stage, consent and token routes are not yet implemented, so
the test covers the authorization request, login, and return to the issuer only.
