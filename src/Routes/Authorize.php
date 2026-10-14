<?php

declare(strict_types=1);

namespace Tualo\Office\OAuth2\Routes;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tualo\Office\Basic\MYSQL\Database;
use Tualo\Office\Basic\Route as BasicRoute;
use Tualo\Office\Basic\Session;
use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\OAuth2\Repositories\IdentityRepository;
use Tualo\Office\OAuth2\Services\AuthorizationServerFactory;
use Tualo\Office\OAuth2\Setup;

final class Authorize extends \Tualo\Office\Basic\RouteWrapper
{
    private const PENDING_SESSION_KEY = 'oauth2_pending_authorizations';
    private const REQUEST_TTL_SECONDS = 600;

    public static function scope(): string
    {
        return 'basic';
    }

    public static function register(): void
    {
        BasicRoute::add('/oauth2/authorize', static function (): bool {
            try {
                $session = App::get('session');
                if (!$session instanceof Session) {
                    throw new \RuntimeException('The Tualo session database is unavailable.');
                }

                $database = $session->getDB();
                if (!$database instanceof Database) {
                    throw new \RuntimeException('The Tualo session database is unavailable.');
                }
                $factory = AuthorizationServerFactory::fromTualoApplication($database);
                $server = $factory->create();
                $identityRepository = new IdentityRepository($database);
                $request = ServerRequest::fromGlobals();
                $query = $request->getQueryParams();
                $requestId = $query['request_id'] ?? null;

                if ($requestId !== null) {
                    if (!is_string($requestId) || !preg_match('/\A[a-f0-9]{64}\z/', $requestId)) {
                        throw OAuthServerException::invalidRequest('request_id');
                    }
                    $pending = self::getPendingRequest($requestId);
                    $authorizationRequest = $pending['authorization_request'];
                } else {
                    $authorizationRequest = $server->validateAuthorizationRequest($request);
                    $requestId = bin2hex(random_bytes(32));
                    $pending = [
                        'authorization_request' => $authorizationRequest,
                        'csrf_token' => bin2hex(random_bytes(32)),
                        'expires_at' => time() + self::REQUEST_TTL_SECONDS,
                    ];
                }

                if (!$session->isLoggedIn()) {
                    self::storePendingRequest($requestId, $pending);
                    $issuer = Setup::getInstance()->env('issuer_url');
                    $continuation = rtrim($issuer, '/') . '/oauth2/authorize?request_id=' . rawurlencode($requestId);
                    $_SESSION['redirect_url'] = $continuation;
                    session_commit();

                    self::emit(new Response(
                        302,
                        ['Location' => self::getLoginUrl($issuer), 'Cache-Control' => 'no-store'],
                        ''
                    ));
                    return true;
                }

                $username = $session->getUsername();
                if (!is_string($username) || $username === '') {
                    throw new \RuntimeException('The authenticated Tualo user is unavailable.');
                }

                $subjectId = $identityRepository->getOrCreateSubjectId($username);
                $authorizationRequest->setUser(
                    $identityRepository->getUserEntityByIdentifier($subjectId)
                );
                $pending['authorization_request'] = $authorizationRequest;
                $pending['expires_at'] = time() + self::REQUEST_TTL_SECONDS;
                self::storePendingRequest($requestId, $pending);

                $issuer = Setup::getInstance()->env('issuer_url');
                $consentUrl = rtrim($issuer, '/') . '/oauth2/consent?request_id=' . rawurlencode($requestId);
                session_commit();

                self::emit(new Response(
                    303,
                    ['Location' => $consentUrl, 'Cache-Control' => 'no-store'],
                    ''
                ));
            } catch (OAuthServerException $exception) {
                self::emit($exception->generateHttpResponse(new Response()));
            } catch (\Throwable $exception) {
                App::logger('oauth2')->error('Authorization request processing failed.', [
                    'exception' => $exception::class,
                ]);
                self::emit(new Response(
                    500,
                    ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store'],
                    json_encode([
                        'error' => 'server_error',
                        'error_description' => 'The authorization request could not be processed.',
                    ], JSON_THROW_ON_ERROR)
                ));
            }

            return true;
        }, ['get'], false, [], self::scope());
    }

    private static function getPendingRequest(string $requestId): array
    {
        $pendingRequests = $_SESSION[self::PENDING_SESSION_KEY] ?? [];
        if (!is_array($pendingRequests) || !isset($pendingRequests[$requestId])) {
            throw OAuthServerException::invalidRequest('request_id');
        }

        $pending = $pendingRequests[$requestId];
        if (
            !is_array($pending)
            || !isset($pending['expires_at'], $pending['authorization_request'], $pending['csrf_token'])
            || !is_int($pending['expires_at'])
            || $pending['expires_at'] < time()
            || !($pending['authorization_request'] instanceof AuthorizationRequestInterface)
            || !is_string($pending['csrf_token'])
        ) {
            unset($_SESSION[self::PENDING_SESSION_KEY][$requestId]);
            throw OAuthServerException::invalidRequest('request_id');
        }

        return $pending;
    }

    private static function storePendingRequest(string $requestId, array $pending): void
    {
        $pendingRequests = $_SESSION[self::PENDING_SESSION_KEY] ?? [];
        if (!is_array($pendingRequests)) {
            $pendingRequests = [];
        }

        foreach ($pendingRequests as $id => $entry) {
            if (!is_array($entry) || !isset($entry['expires_at']) || $entry['expires_at'] < time()) {
                unset($pendingRequests[$id]);
            }
        }

        if (!isset($pendingRequests[$requestId]) && count($pendingRequests) >= 10) {
            throw OAuthServerException::invalidRequest(
                'request_id',
                'Too many authorization requests are pending in this session.'
            );
        }

        $pendingRequests[$requestId] = $pending;
        $_SESSION[self::PENDING_SESSION_KEY] = $pendingRequests;
    }

    private static function getLoginUrl(string $issuer): string
    {
        $parts = parse_url($issuer);
        if (
            $parts === false
            || ($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new \RuntimeException('The configured OAuth issuer URL is invalid.');
        }

        $authority = $parts['host'];
        if (isset($parts['port'])) {
            $authority .= ':' . $parts['port'];
        }

        return 'https://' . $authority . '/';
    }

    private static function emit(ResponseInterface $response): void
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
