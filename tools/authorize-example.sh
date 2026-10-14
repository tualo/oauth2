#!/usr/bin/env bash

set -euo pipefail

: "${OAUTH2_ISSUER_URL:?Set OAUTH2_ISSUER_URL to the configured HTTPS issuer URL}"
: "${OAUTH2_CLIENT_ID:?Set OAUTH2_CLIENT_ID to a registered OAuth client ID}"
: "${OAUTH2_REDIRECT_URI:?Set OAUTH2_REDIRECT_URI to a redirect URI registered for that client}"

for command_name in openssl php; do
    if ! command -v "$command_name" >/dev/null 2>&1; then
        printf 'Required command not found: %s\n' "$command_name" >&2
        exit 1
    fi
done

state="$(openssl rand -hex 32)"
code_verifier="$(openssl rand -hex 32)"
code_challenge="$(
    printf '%s' "$code_verifier" \
        | openssl dgst -sha256 -binary \
        | openssl base64 -A \
        | tr '+/' '-_' \
        | tr -d '='
)"

authorize_url="$(
    php -r '
        [$script, $issuer, $clientId, $redirectUri, $state, $codeChallenge] = $argv;
        $parts = parse_url($issuer);
        if (
            $parts === false
            || ($parts["scheme"] ?? "") !== "https"
            || empty($parts["host"])
            || isset($parts["user"])
            || isset($parts["pass"])
            || isset($parts["query"])
            || isset($parts["fragment"])
        ) {
            fwrite(STDERR, "OAUTH2_ISSUER_URL must be an HTTPS issuer URL without credentials, query, or fragment\n");
            exit(1);
        }

        $redirectParts = parse_url($redirectUri);
        if (
            $redirectParts === false
            || !in_array($redirectParts["scheme"] ?? "", ["http", "https"], true)
            || empty($redirectParts["host"])
            || isset($redirectParts["user"])
            || isset($redirectParts["pass"])
        ) {
            fwrite(STDERR, "OAUTH2_REDIRECT_URI must be an absolute HTTP or HTTPS URI, for example https://client.example/callback\n");
            exit(1);
        }

        $query = http_build_query([
            "response_type" => "code",
            "client_id" => $clientId,
            "redirect_uri" => $redirectUri,
            "scope" => "openid profile",
            "state" => $state,
            "code_challenge" => $codeChallenge,
            "code_challenge_method" => "S256",
        ], "", "&", PHP_QUERY_RFC3986);
        echo rtrim($issuer, "/") . "/oauth2/authorize?" . $query;
    ' "$OAUTH2_ISSUER_URL" "$OAUTH2_CLIENT_ID" "$OAUTH2_REDIRECT_URI" "$state" "$code_challenge"
)"

printf 'Opening OAuth authorization request:\n%s\n\n' "$authorize_url"
printf 'state: %s\n' "$state"
printf 'PKCE code_verifier (keep for a later token exchange): %s\n' "$code_verifier"
printf '\n'

if [[ "${OPEN_BROWSER:-1}" == "1" ]]; then
    if command -v open >/dev/null 2>&1; then
        open "$authorize_url"
    elif command -v xdg-open >/dev/null 2>&1; then
        xdg-open "$authorize_url" >/dev/null 2>&1
    else
        printf 'No browser launcher found. Set OPEN_BROWSER=0 and open the URL manually.\n' >&2
        exit 1
    fi
fi
