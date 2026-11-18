<?php

declare(strict_types=1);

namespace Tualo\Office\OAuth2\Routes;

use Tualo\Office\Basic\Route as BasicRoute;
use Tualo\Office\Basic\TualoApplication as App;

/**
 * Out-of-band style redirect target for clients that cannot receive a
 * callback (firewalls, NAT, no listener). Register
 * `<issuer>/oauth2/manual-code` as the client's redirect URI; after consent
 * the browser lands here and the user copies the displayed code into the
 * client, which then performs the regular token exchange.
 */
final class ManualCode extends \Tualo\Office\Basic\RouteWrapper
{
    public static function scope(): string
    {
        return 'basic';
    }

    public static function register(): void
    {
        BasicRoute::add('/oauth2/manual-code', static function (): bool {
            header('Cache-Control: no-store');
            header('Referrer-Policy: no-referrer');
            App::contenttype('text/html; charset=utf-8');

            $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

            $body = '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">'
                . '<meta name="robots" content="noindex">'
                . '<title>Autorisierungscode</title></head>'
                . '<body style="font-family: sans-serif; max-width: 40em; margin: 3em auto;">';

            $error = $_GET['error'] ?? null;
            $code = $_GET['code'] ?? null;
            $state = $_GET['state'] ?? null;

            if (is_string($error)) {
                $body .= '<h1>Anfrage abgelehnt</h1>'
                    . '<p><b>' . $escape($error) . '</b></p>';
                $description = $_GET['error_description'] ?? null;
                if (is_string($description)) {
                    $body .= '<p>' . $escape($description) . '</p>';
                }
            } elseif (is_string($code) && $code !== '') {
                $body .= '<h1>Einwilligung erteilt</h1>'
                    . '<p><b>Übertragen Sie den folgenden Code jetzt in die anfragende Anwendung.</b> '
                    . 'Der Code ist nur kurze Zeit und nur einmal verwendbar.</p>'
                    . '<p><textarea readonly rows="3" style="width: 100%; font-family: monospace;" '
                    . 'onclick="this.select()">' . $escape($code) . '</textarea></p>';
                if (is_string($state) && $state !== '') {
                    $body .= '<p>Zugehöriger <code>state</code> (zur Kontrolle in der Anwendung):</p>'
                        . '<p><code style="word-break: break-all;">' . $escape($state) . '</code></p>';
                }
                $body .= '<p><b>Geben Sie den Code niemals an Dritte weiter.</b> '
                    . 'Wenn Sie diese Seite nicht selbst durch eine Anmeldung erreicht haben, '
                    . 'verwenden Sie den Code nicht.</p>';
            } else {
                http_response_code(400);
                $body .= '<h1>Unvollständige Anfrage</h1>'
                    . '<p>Es wurde weder <code>code</code> noch <code>error</code> übergeben.</p>';
            }

            $body .= '<p>Sie können dieses Fenster anschließend schließen.</p></body></html>';
            App::body($body);

            return true;
        }, ['get'], true, [], self::scope());
    }
}
