# Architektur

## Protokollumfang

Das Paket stellt einen OAuth 2.0 Authorization Server mit OpenID Connect bereit.
Für Benutzer werden Authorization Code und PKCE verwendet; vertrauliche
Maschinen-Clients können Client Credentials verwenden. Implicit Grant und
Resource Owner Password Credentials werden nicht angeboten.

OAuth- und OIDC-Protokollverarbeitung sowie Token-Signaturen werden an
`league/oauth2-server` und `steverhoades/oauth2-openid-connect-server`
delegiert. Tualo-spezifische Routen, Repositories, Persistenz und Oberfläche
werden in diesem Paket implementiert.

## Vertrauensgrenzen

- Der bestehende Tualo-Login authentifiziert Endbenutzer. OAuth-Clients
  ersetzen keine Benutzeranmeldung.
- Redirect-URIs werden pro Client registriert und exakt validiert.
- PKCE ist für Authorization-Code-Anfragen verpflichtend.
- Authorization-Code-Anfragen müssen `code_challenge_method=S256` verwenden;
  `plain` wird nicht akzeptiert.
- Client-Credentials-Clients erhalten keine Benutzeridentität und keine
  OpenID-Connect-ID-Tokens.
- Access Tokens und ID Tokens werden asymmetrisch signiert. Private PEM-Schlüssel
  liegen außerhalb des Webroots; nur die öffentlichen Schlüssel werden über
  JWKS veröffentlicht.
- OIDC-`sub` ist eine opake UUID und wird dauerhaft auf den Tualo-Login
  abgebildet. Benutzername und Anzeigename werden nur als `profile`-Claims
  `preferred_username` und `name` freigegeben.
- OAuth-Clients und deren erlaubte Redirect-URIs und Scopes werden durch
  berechtigte Administratoren verwaltet. Es gibt keine offene Client-Registrierung.
- Token, Authorization Codes und Client-Geheimnisse werden nicht in Logs
  geschrieben.
- Laufzeiten werden über `Setup::getInstance()->env()` bezogen:
  `auth_token_ttl` steuert Authorization Codes (Default `PT5M`),
  `access_token_ttl` Access Tokens (Default `PT15M`) und
  `refresh_token_ttl` Refresh Tokens (Default `P30D`). Refresh Tokens werden
  nur mit `offline_access` ausgegeben und bei jeder Verwendung rotiert.
- Client Secrets werden ausschließlich als Passwort-Hash gespeichert.

## Paketgrenzen

- `Routes/` übersetzt HTTP-Anfragen in die APIs der Protokollbibliotheken.
- `Services/` konfiguriert den Authorization Server und integriert Tualo-Sessions.
- `Repositories/` implementiert die von den Bibliotheken erwarteten
  Client-, Benutzer-, Scope-, Code- und Token-Repositories.
- `Entities/` bildet Clients, Benutzeridentitäten, Scopes und Claims ab.
- Die Administration verwaltet Clients; Geheimnisse werden nur bei ihrer
  Erstellung ausgegeben und danach nicht erneut angezeigt.

## Endpunktvertrag

Alle Endpunkte gehören zum konfigurierten Issuer. Der Issuer wird nicht aus
dem `Host`-Header abgeleitet. Die folgenden Pfade sind die öffentliche
Schnittstelle des ersten Releases.

| Methode und Pfad | Zugriff | Zweck |
|---|---|---|
| `GET /.well-known/openid-configuration` | öffentlich | OIDC-Discovery-Metadaten |
| `GET /oauth2/authorize` | Browser, Tualo-Session erforderlich | Authorization Request validieren und Anmeldung/Einwilligung starten |
| `GET /oauth2/consent` | Browser, Tualo-Session erforderlich | Client, angeforderte Scopes und angemeldeten Benutzer anzeigen |
| `POST /oauth2/consent` | Browser, Tualo-Session erforderlich | Einwilligung erteilen oder ablehnen und Authorization Response abschließen |
| `POST /oauth2/token` | öffentlich; Client-Authentifizierung grantabhängig | Authorization Code, Client Credentials und Refresh Token gegen Tokens tauschen |
| `GET /oauth2/jwks` | öffentlich | Öffentliche Signaturschlüssel für JWT-Prüfung |
| `GET` oder `POST /oauth2/userinfo` | Authorization-Header erforderlich | OIDC-Claims für den authentifizierten Benutzer liefern |

### Discovery

`GET /.well-known/openid-configuration` liefert `application/json` mit dem
konfigurierten `issuer` und den vollständigen absoluten URLs der Endpunkte
`authorization_endpoint`, `token_endpoint`, `userinfo_endpoint` und
`jwks_uri`. Die Metadaten kündigen `response_types_supported=["code"]`,
`subject_types_supported=["public"]`, `id_token_signing_alg_values_supported=["RS256"]`,
die Grants Authorization Code, Client Credentials und Refresh Token sowie
PKCE ausschließlich mit `S256` an. Am Token-Endpunkt sind
`client_secret_basic` und `client_secret_post` für vertrauliche Clients
vorgesehen.

### Authorization und Einwilligung

`GET /oauth2/authorize` akzeptiert `response_type=code`, `client_id`,
`redirect_uri`, `scope`, `state`, `code_challenge` und
`code_challenge_method=S256`. Ungültige Requests werden nicht an eine
Redirect-URI weitergeleitet, bevor Client und Redirect-URI verifiziert wurden.
Eine fehlende Tualo-Session setzt den normalen Tualo-Login fort und erhält die
Authorization Request für die Rückkehr.

Bei angemeldetem Benutzer wird eine separate Consent-Seite unter
`/oauth2/consent` gezeigt. Sie zeigt Clientnamen und alle angeforderten Scopes
und bietet explizite Aktionen zum Erteilen oder Ablehnen. Die Zustimmung muss
per geschütztem POST erfolgen; Ablehnung erzeugt eine standardisierte
`access_denied`-Antwort an die bereits validierte Redirect-URI und erhält
gegebenenfalls `state`. Erteilung stellt einen kurzlebigen Authorization Code
mit PKCE-Bindung aus und leitet ausschließlich zur registrierten Redirect-URI
weiter.

### Token

`POST /oauth2/token` akzeptiert `application/x-www-form-urlencoded` und
unterstützt:

- `grant_type=authorization_code` mit `code`, `redirect_uri` und
  `code_verifier`; vertrauliche Clients authentifizieren sich mit
  `client_secret_basic` oder `client_secret_post`.
- `grant_type=client_credentials` für vertrauliche Clients; dieser Grant
  akzeptiert keine OIDC-Scopes oder Benutzeridentität.
- `grant_type=refresh_token` mit einem zuvor ausgegebenen Refresh Token.
  Ein Refresh Token wird nur ausgegeben, wenn `offline_access` erteilt wurde,
  und bei erfolgreicher Verwendung rotiert.

Erfolg und Fehler folgen dem OAuth-Token-Response-Format; Antworten enthalten
`Cache-Control: no-store` und `Pragma: no-cache`. Client Secrets, Codes und
Tokens werden nicht in Logs aufgenommen.

### JWKS und UserInfo

`GET /oauth2/jwks` liefert ausschließlich öffentliche Schlüssel,
die zur Prüfung aktuell gültiger Access- und ID-Tokens benötigt werden. Private
Schlüsselmaterialien werden nie ausgegeben.

`GET` und `POST /oauth2/userinfo` validieren das im Authorization-Header
übermittelte Zugriffstoken serverseitig und geben nur Claims aus, die durch
dessen Scopes erlaubt sind.
`openid` liefert mindestens `sub`; `profile` ergänzt `preferred_username` und
`name`. `client_credentials`-Tokens sind für UserInfo nicht zulässig. Antworten
verwenden `application/json` und bei ungültigem Token `401` mit
`WWW-Authenticate: Bearer`.

Alle OAuth-Protokollfehler verwenden das standardisierte OAuth-Fehlerformat
und den dafür passenden HTTP-Status. Fehlerantworten legen keine internen
Ausnahmen, Schlüsselpfade oder Datenbankdetails offen.

Schlüsselpfad, Passphrase und Verschlüsselungsschlüssel werden explizit an die
Server-Factory übergeben. Der private Schlüssel muss lesbar und außerhalb des
Webroots liegen; es gibt dafür keine unsicheren Laufzeit-Defaults.

Der Issuer wird über `Setup::getInstance()->env('issuer_url')` bezogen.
ID-Tokens verwenden ausschließlich diesen konfigurierten Wert und niemals
einen aus der HTTP-Anfrage abgeleiteten Hostnamen.
