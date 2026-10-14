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

## Geplante Endpunkte

- `/.well-known/openid-configuration` für Provider-Metadaten
- `/oauth2/authorize` für Benutzerautorisierung und Einwilligung
- `/oauth2/token` für Authorization Code und Client Credentials
- `/oauth2/jwks` für öffentliche Signaturschlüssel
- `/oauth2/userinfo` für OIDC-Benutzerinformationen

Schlüsselpfad, Passphrase und Verschlüsselungsschlüssel werden explizit an die
Server-Factory übergeben. Der private Schlüssel muss lesbar und außerhalb des
Webroots liegen; es gibt dafür keine unsicheren Laufzeit-Defaults.

Der Issuer wird über `Setup::getInstance()->env('issuer_url')` bezogen.
ID-Tokens verwenden ausschließlich diesen konfigurierten Wert und niemals
einen aus der HTTP-Anfrage abgeleiteten Hostnamen.
