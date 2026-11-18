<?php

declare(strict_types=1);

namespace Tualo\Office\OAuth2\Services;

use Psr\Http\Message\ResponseInterface;
use Tualo\Office\Basic\TualoApplication as App;

final class PsrResponseEmitter
{
    public static function emit(ResponseInterface $response): void
    {
        http_response_code($response->getStatusCode());

        foreach ($response->getHeaders() as $name => $values) {
            header($name . ': ' . implode(', ', $values), true);
        }
        if (!$response->hasHeader('Cache-Control')) {
            header('Cache-Control: no-store', true);
        }
        if (!$response->hasHeader('Pragma')) {
            header('Pragma: no-cache', true);
        }

        $contentType = $response->getHeaderLine('Content-Type');
        App::contenttype($contentType !== '' ? $contentType : 'text/plain');
        App::body((string) $response->getBody());
    }
}
