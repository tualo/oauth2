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
            "scope" => "openid profile offline_access encrypted-data-keys",
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

# When the redirect URI points to the loopback interface, serve the callback
# locally and wait for the authorization code issued after consent.
callback_listen="$(
    php -r '
        $parts = parse_url($argv[1]);
        $host = $parts["host"] ?? "";
        if (($parts["scheme"] ?? "") !== "http" || !in_array($host, ["127.0.0.1", "localhost", "[::1]"], true)) {
            exit(0);
        }
        echo $host, ":", $parts["port"] ?? 80, " ", $parts["path"] ?? "/";
    ' "$OAUTH2_REDIRECT_URI"
)"

callback_pid=""
result_file=""
result_file_is_temp=0
cleanup() {
    if [[ -n "$callback_pid" ]] && kill -0 "$callback_pid" 2>/dev/null; then
        kill "$callback_pid" 2>/dev/null || true
        wait "$callback_pid" 2>/dev/null || true
    fi
    if [[ -n "$result_file" && "$result_file_is_temp" == "1" ]]; then
        rm -f "$result_file"
    fi
}
trap cleanup EXIT

if [[ -n "$callback_listen" ]]; then
    listen_address="${callback_listen%% *}"
    callback_path="${callback_listen#* }"

    if [[ -n "${OAUTH2_CALLBACK_RESULT_FILE:-}" ]]; then
        result_file="$OAUTH2_CALLBACK_RESULT_FILE"
    else
        result_file="$(mktemp)"
        result_file_is_temp=1
    fi
    : > "$result_file"

    OAUTH2_CALLBACK_PATH="$callback_path" \
    OAUTH2_CALLBACK_RESULT_FILE="$result_file" \
        php -S "$listen_address" "$(cd "$(dirname "$0")" && pwd)/callback-server.php" \
        >/dev/null 2>&1 &
    callback_pid="$!"
    sleep 0.3
    if ! kill -0 "$callback_pid" 2>/dev/null; then
        printf 'Could not start the callback listener on %s (port in use?).\n' "$listen_address" >&2
        exit 1
    fi
    printf 'Waiting for the authorization code on http://%s%s ...\n\n' "$listen_address" "$callback_path"
fi

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

if [[ -z "$callback_listen" ]]; then
    exit 0
fi

timeout_seconds="${OAUTH2_CALLBACK_TIMEOUT:-300}"
waited=0
while [[ ! -s "$result_file" ]]; do
    if (( waited >= timeout_seconds )); then
        printf 'Timed out after %s seconds waiting for the authorization code.\n' "$timeout_seconds" >&2
        exit 1
    fi
    sleep 1
    waited=$((waited + 1))
done

php -r '
    [$script, $resultFile, $expectedState, $issuer, $clientId, $redirectUri, $codeVerifier] = $argv;
    $params = json_decode((string) file_get_contents($resultFile), true);
    if (!is_array($params)) {
        fwrite(STDERR, "The callback result could not be read.\n");
        exit(1);
    }
    if (isset($params["error"])) {
        fwrite(STDERR, "Authorization failed: " . $params["error"]
            . (isset($params["error_description"]) ? " - " . $params["error_description"] : "") . "\n");
        exit(1);
    }
    if (!isset($params["code"], $params["state"]) || !hash_equals($expectedState, (string) $params["state"])) {
        fwrite(STDERR, "The callback is missing a code or returned an unexpected state.\n");
        exit(1);
    }
    echo "Authorization code received:\n", $params["code"], "\n\n";
    echo "Exchange it for tokens:\n";
    echo "curl -k -X POST \"", rtrim($issuer, "/"), "/oauth2/token\" \\\n";
    echo "  -d grant_type=authorization_code \\\n";
    echo "  -d client_id=", escapeshellarg($clientId), " \\\n";
    echo "  -d redirect_uri=", escapeshellarg($redirectUri), " \\\n";
    echo "  -d code_verifier=", escapeshellarg($codeVerifier), " \\\n";
    echo "  -d code=", escapeshellarg($params["code"]), "\n";
' "$result_file" "$state" "$OAUTH2_ISSUER_URL" "$OAUTH2_CLIENT_ID" "$OAUTH2_REDIRECT_URI" "$code_verifier"
