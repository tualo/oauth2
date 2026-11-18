<?php

declare(strict_types=1);

/**
 * Router for `php -S`: receives the OAuth redirect on the loopback
 * interface, stores the query parameters for the waiting shell script,
 * and shows the authorization code in the browser.
 */

$expectedPath = getenv('OAUTH2_CALLBACK_PATH') ?: '/callback';
$resultFile = getenv('OAUTH2_CALLBACK_RESULT_FILE') ?: '';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($path !== $expectedPath) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not found\n";
    return true;
}

if ($resultFile !== '') {
    file_put_contents(
        $resultFile,
        json_encode($_GET, JSON_THROW_ON_ERROR) . "\n",
        LOCK_EX
    );
    if (file_exists($resultFile)) {
        chmod($resultFile, 0600);
    }
}

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

echo "<!DOCTYPE html>\n<html lang=\"de\"><head><meta charset=\"utf-8\">";
echo '<title>OAuth2-Callback</title></head>';
echo '<body style="font-family: sans-serif; max-width: 40em; margin: 3em auto;">';

if (isset($_GET['error'])) {
    echo '<h1>Anfrage abgelehnt</h1>';
    echo '<p><b>' . $escape((string) $_GET['error']) . '</b></p>';
    if (isset($_GET['error_description'])) {
        echo '<p>' . $escape((string) $_GET['error_description']) . '</p>';
    }
} elseif (isset($_GET['code'])) {
    echo '<h1>Einwilligung erteilt</h1>';
    echo '<p>Autorisierungscode:</p>';
    echo '<p><code style="word-break: break-all;">' . $escape((string) $_GET['code']) . '</code></p>';
    echo '<p>Der Code wurde an das wartende Testscript übergeben.</p>';
} else {
    echo '<h1>Unvollständiger Callback</h1>';
    echo '<p>Es wurde weder <code>code</code> noch <code>error</code> übergeben.</p>';
}

echo '<p>Sie können dieses Fenster schließen.</p></body></html>';

return true;
