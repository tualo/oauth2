#!/usr/bin/env bash

set -euo pipefail

: "${OAUTH2_ISSUER_URL:?Set OAUTH2_ISSUER_URL to the configured HTTPS issuer URL}"
: "${OAUTH2_CLIENT_ID:?Set OAUTH2_CLIENT_ID to a registered OAuth client ID}"

scope="${OAUTH2_SCOPE:-openid profile offline_access}"
issuer="${OAUTH2_ISSUER_URL%/}"
curl_opts=(-s)
if [[ "${OAUTH2_INSECURE:-0}" == "1" ]]; then
    curl_opts+=(-k)
fi

for command_name in curl php; do
    if ! command -v "$command_name" >/dev/null 2>&1; then
        printf 'Required command not found: %s\n' "$command_name" >&2
        exit 1
    fi
done

printf 'Requesting device authorization from %s ...\n' "$issuer/oauth2/device_authorization"
device_response="$(
    curl "${curl_opts[@]}" -X POST "$issuer/oauth2/device_authorization" \
        -d "client_id=$OAUTH2_CLIENT_ID" \
        --data-urlencode "scope=$scope"
)"

eval "$(
    php -r '
        $data = json_decode($argv[1], true);
        if (!is_array($data) || isset($data["error"])) {
            fwrite(STDERR, "Device authorization failed: " . $argv[1] . "\n");
            echo "exit 1;";
            exit(0);
        }
        foreach (["device_code", "user_code", "verification_uri"] as $key) {
            if (!isset($data[$key])) {
                fwrite(STDERR, "Missing \"$key\" in the device authorization response.\n");
                echo "exit 1;";
                exit(0);
            }
        }
        printf("device_code=%s\n", escapeshellarg($data["device_code"]));
        printf("user_code=%s\n", escapeshellarg($data["user_code"]));
        printf("verification_uri=%s\n", escapeshellarg($data["verification_uri"]));
        printf("verification_uri_complete=%s\n", escapeshellarg($data["verification_uri_complete"] ?? ""));
        printf("interval=%s\n", escapeshellarg((string) ($data["interval"] ?? 5)));
        printf("expires_in=%s\n", escapeshellarg((string) ($data["expires_in"] ?? 600)));
    ' "$device_response"
)"

printf '\nOpen the verification page and enter the code:\n'
printf '  Verification URI: %s\n' "$verification_uri"
printf '  User code:        %s\n\n' "$user_code"

open_target="$verification_uri"
if [[ -n "$verification_uri_complete" ]]; then
    open_target="$verification_uri_complete"
fi
if [[ "${OPEN_BROWSER:-1}" == "1" ]]; then
    if command -v open >/dev/null 2>&1; then
        open "$open_target"
    elif command -v xdg-open >/dev/null 2>&1; then
        xdg-open "$open_target" >/dev/null 2>&1
    fi
fi

printf 'Polling the token endpoint every %s seconds (expires in %s seconds) ...\n' "$interval" "$expires_in"
deadline=$(( $(date +%s) + expires_in ))
while true; do
    if (( $(date +%s) >= deadline )); then
        printf 'The device code expired before the request was approved.\n' >&2
        exit 1
    fi
    sleep "$interval"

    token_response="$(
        curl "${curl_opts[@]}" -X POST "$issuer/server/oauth2/token" \
            -d grant_type=urn:ietf:params:oauth:grant-type:device_code \
            -d "client_id=$OAUTH2_CLIENT_ID" \
            -d "device_code=$device_code"
    )"

    status="$(
        php -r '
            $data = json_decode($argv[1], true);
            if (!is_array($data)) { echo "malformed"; exit(0); }
            if (isset($data["access_token"])) { echo "success"; exit(0); }
            echo $data["error"] ?? "malformed";
        ' "$token_response"
    )"

    case "$status" in
        success)
            printf '\nTokens received:\n%s\n' "$(php -r 'echo json_encode(json_decode($argv[1]), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);' "$token_response")"
            exit 0
            ;;
        authorization_pending)
            printf '.'
            ;;
        slow_down)
            interval=$((interval + 5))
            printf 'slow_down: increasing the polling interval to %s seconds\n' "$interval"
            ;;
        access_denied)
            printf '\nThe request was denied by the user.\n' >&2
            exit 1
            ;;
        expired_token)
            printf '\nThe device code expired before the request was approved.\n' >&2
            exit 1
            ;;
        *)
            printf '\nToken request failed: %s\n' "$token_response" >&2
            exit 1
            ;;
    esac
done
