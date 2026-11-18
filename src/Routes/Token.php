<?php

declare(strict_types=1);

namespace Tualo\Office\OAuth2\Routes;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use League\OAuth2\Server\Exception\OAuthServerException;
use Tualo\Office\Basic\MYSQL\Database;
use Tualo\Office\Basic\Route as BasicRoute;
use Tualo\Office\Basic\Session;
use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\OAuth2\Services\AuthorizationServerFactory;
use Tualo\Office\OAuth2\Services\PsrResponseEmitter;

final class Token extends \Tualo\Office\Basic\RouteWrapper
{
    public static function scope(): string
    {
        return 'basic';
    }

    public static function register(): void
    {
        BasicRoute::add('/oauth2/token', static function (): bool {
            try {
                $session = App::get('session');
                if (!$session instanceof Session || !$session->db instanceof Database) {
                    throw new \RuntimeException('The Tualo session database is unavailable.');
                }

                $server = AuthorizationServerFactory::fromTualoApplication($session->db)->create();
                $response = $server->respondToAccessTokenRequest(
                    ServerRequest::fromGlobals(),
                    new Response()
                );
                PsrResponseEmitter::emit($response);
            } catch (OAuthServerException $exception) {
                PsrResponseEmitter::emit($exception->generateHttpResponse(new Response()));
            } catch (\Throwable $exception) {
                App::logger('oauth2')->error('Token request processing failed.', [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                    'code' => $exception->getCode(),
                    'file' => basename($exception->getFile()),
                    'line' => $exception->getLine(),
                ]);
                PsrResponseEmitter::emit(new Response(
                    500,
                    ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store'],
                    json_encode([
                        'error' => 'server_error',
                        'error_description' => 'The token request could not be processed.',
                    ], JSON_THROW_ON_ERROR)
                ));
            }

            return true;
        }, ['post'], false, [], self::scope());
    }
}
