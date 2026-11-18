<?php

namespace Tualo\Office\OAuth2\Routes;

use GuzzleHttp\Psr7\Response;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use Tualo\Office\Basic\MYSQL\Database;
use Tualo\Office\Basic\Route as BasicRoute;
use Tualo\Office\Basic\Session;
use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\OAuth2\Entities\OAuthUser;
use Tualo\Office\OAuth2\Services\AuthorizationServerFactory;

class Consent extends \Tualo\Office\Basic\RouteWrapper
{
    private const PENDING_SESSION_KEY = 'oauth2_pending_authorizations';

    public static function scope(): string
    {
        return 'basic';
    }
    public static function register()
    {
        BasicRoute::add('/oauth2/consent', function ($matches) {
            $request_id = $_GET['request_id'] ?? null;
            if (!$request_id) {
                throw new \Exception('Request ID is required.');
            }
            #weiterleiten zu 'Location: #oauth2-consent/' . urlencode($request_id)

            header('Location: ../#oauth2-consent/' . urlencode($request_id));
        }, ['get'], true, [], self::scope());

        BasicRoute::add('/oauth2/consent', function ($matches) {
            App::contenttype('application/json');
            header('Cache-Control: no-store');
            App::result('success', false);
            $stage = 'input_validation';
            try {
                $input = $_POST;
                if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
                    $decoded = json_decode(file_get_contents('php://input'), true);
                    $input = is_array($decoded) ? $decoded : [];
                }

                $request_id = $input['request_id'] ?? null;
                if (!is_string($request_id) || !preg_match('/\A[a-f0-9]{64}\z/', $request_id)) {
                    throw new \Exception('A valid request ID is required.', 15403);
                }

                $decision = $input['decision'] ?? null;
                if (!in_array($decision, ['approve', 'deny'], true)) {
                    throw new \Exception('The decision must be "approve" or "deny".', 15400);
                }

                $stage = 'pending_request';
                $pendingRequests = $_SESSION[self::PENDING_SESSION_KEY] ?? [];
                $pending = is_array($pendingRequests) ? ($pendingRequests[$request_id] ?? null) : null;
                if (
                    !is_array($pending)
                    || !isset($pending['expires_at'], $pending['authorization_request'], $pending['csrf_token'])
                    || !is_int($pending['expires_at'])
                    || $pending['expires_at'] <= time()
                    || !($pending['authorization_request'] instanceof AuthorizationRequestInterface)
                    || !is_string($pending['csrf_token'])
                ) {
                    unset($_SESSION[self::PENDING_SESSION_KEY][$request_id]);
                    throw new \Exception('The authorization request is invalid or has expired.', 15403);
                }

                $stage = 'csrf_validation';
                $csrf_token = $input['csrf_token'] ?? null;
                if (!is_string($csrf_token) || !hash_equals($pending['csrf_token'], $csrf_token)) {
                    throw new \Exception('The consent request could not be verified.', 15403);
                }

                $stage = 'user_validation';
                $authorizationRequest = $pending['authorization_request'];
                $session = App::get('session');
                $user = $authorizationRequest->getUser();
                if (
                    !$session instanceof Session
                    || !$session->isLoggedIn()
                    || !$user instanceof OAuthUser
                    || $user->getClaims()['preferred_username'] !== $session->getUsername()
                ) {
                    throw new \Exception('The authorization request does not belong to the current user.', 15403);
                }

                $database = $session->db;
                if (!$database instanceof Database) {
                    throw new \RuntimeException('The Tualo session database is unavailable.');
                }

                $stage = 'server_configuration';
                $server = AuthorizationServerFactory::fromTualoApplication($database)->create();

                // The request is single-use: consume it before completing.
                unset($_SESSION[self::PENDING_SESSION_KEY][$request_id]);
                session_commit();

                $stage = 'authorization_completion';
                $authorizationRequest->setAuthorizationApproved($decision === 'approve');
                try {
                    $response = $server->completeAuthorizationRequest($authorizationRequest, new Response());
                } catch (OAuthServerException $exception) {
                    if ($exception->hasRedirect()) {
                        // Denial and comparable protocol errors redirect back to the client.
                        $response = $exception->generateHttpResponse(new Response());
                    } else {
                        throw $exception;
                    }
                }

                $redirectUri = $response->getHeaderLine('Location');
                if ($redirectUri === '') {
                    throw new \RuntimeException('The authorization response is missing a redirect target.');
                }

                App::result('redirect_uri', $redirectUri);
                App::result('success', true);
            } catch (\Exception $e) {
                if ($e->getCode() == 15403) {
                    http_response_code(403);
                } elseif ($e->getCode() == 15400) {
                    http_response_code(400);
                } else {
                    http_response_code(500);
                    App::logger('oauth2')->error('Consent decision processing failed.', [
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                        'code' => $e->getCode(),
                        'stage' => $stage,
                        'file' => basename($e->getFile()),
                        'line' => $e->getLine(),
                    ]);
                }
                App::result('code', $e->getCode());
                App::result('msg', $e->getCode() == 15403 || $e->getCode() == 15400
                    ? $e->getMessage()
                    : 'The consent decision could not be processed.');
            }
        }, ['post'], true, [], self::scope());
    }
}
