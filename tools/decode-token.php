<?php

/**
 * Decode (and optionally verify) an OAuth2 access token (JWT).
 *
 * Usage:
 *   php tools/decode-token.php <token> [keyfile]
 *
 *   <token>   the JWT from the Authorization header (without "Bearer ")
 *   [keyfile] optional path to the private or public key (PEM) to verify
 *             the RS256 signature (e.g. the oauth2.private_key_path)
 */

if ($argc < 2) {
    fwrite(STDERR, "Usage: php tools/decode-token.php <token> [keyfile]\n");
    exit(1);
}

$token = trim($argv[1]);
$keyFile = $argv[2] ?? null;

$parts = explode('.', $token);
if (count($parts) !== 3) {
    fwrite(STDERR, "Error: not a JWT (expected header.payload.signature)\n");
    exit(1);
}

$b64 = static function (string $data): string|false {
    return base64_decode(strtr($data, '-_', '+/'), true);
};

[$headerRaw, $payloadRaw, $signatureRaw] = $parts;
$header = json_decode((string)$b64($headerRaw), true);
$payload = json_decode((string)$b64($payloadRaw), true);

if (!is_array($header) || !is_array($payload)) {
    fwrite(STDERR, "Error: unable to decode JWT header/payload\n");
    exit(1);
}

echo "Header:\n" . json_encode($header, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
echo "Payload:\n" . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

foreach (['iat' => 'issued at', 'nbf' => 'not before', 'exp' => 'expires at'] as $claim => $label) {
    if (isset($payload[$claim])) {
        printf("%-10s %s (%s)\n", $claim, date('Y-m-d H:i:s', (int)$payload[$claim]), $label);
    }
}
if (isset($payload['exp'])) {
    echo ((int)$payload['exp'] < time()) ? "=> token is EXPIRED\n" : "=> token is still valid (time-wise)\n";
}

if ($keyFile !== null) {
    echo "\nSignature check:\n";
    $keyData = file_get_contents($keyFile);
    if ($keyData === false) {
        fwrite(STDERR, "Error: unable to read key file $keyFile\n");
        exit(1);
    }
    $key = openssl_pkey_get_private($keyData) ?: openssl_pkey_get_public($keyData);
    if ($key === false) {
        fwrite(STDERR, "Error: unable to load key from $keyFile\n");
        exit(1);
    }
    $details = openssl_pkey_get_details($key);
    $publicKey = openssl_pkey_get_public($details['key']);
    $result = openssl_verify(
        $headerRaw . '.' . $payloadRaw,
        (string)$b64($signatureRaw),
        $publicKey,
        OPENSSL_ALGO_SHA256
    );
    echo $result === 1 ? "=> signature VALID\n" : "=> signature INVALID\n";
    exit($result === 1 ? 0 : 2);
}
