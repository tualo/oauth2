<?php

namespace Tualo\Office\OAuth2\Routes;

use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use Tualo\Office\Basic\Route as BasicRoute;
use Tualo\Office\Basic\Session;
use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\OAuth2\Entities\OAuthUser;
use Tualo\Office\OAuth2\Scopes\OAuth2ConsentInformationScope;
use Tualo\Office\OAuth2\Services\ScopeCatalog;
use Tualo\Office\OAuth2\Setup;

class ConsentInformation extends \Tualo\Office\Basic\RouteWrapper
{
    const DefaultExpectedFields  = [
        'request_id' => [
            'required' => true,
            'type' => 'string',
            'minlength' => 0,
            'maxlength' => 1000
        ]
    ];
    public static function scope(): string
    {
        return OAuth2ConsentInformationScope::identifier();
    }
    public static function register()
    {
        BasicRoute::add('/oauth2/consentinformation', function ($matches) {
            App::contenttype('application/json');
            header('Cache-Control: no-store');
            App::result('success', false);
            try {
                $request_id = $_GET['request_id'] ?? null;
                if (!is_string($request_id) || !preg_match('/\A[a-f0-9]{64}\z/', $request_id)) {
                    throw new \Exception('A valid request ID is required.', 15403);
                }
                $pendingRequests = $_SESSION['oauth2_pending_authorizations'] ?? [];
                $pending = is_array($pendingRequests) ? ($pendingRequests[$request_id] ?? null) : null;
                if (
                    !is_array($pending)
                    || !isset($pending['expires_at'], $pending['authorization_request'], $pending['csrf_token'])
                    || !is_int($pending['expires_at'])
                    || $pending['expires_at'] <= time()
                    || !($pending['authorization_request'] instanceof AuthorizationRequestInterface)
                    || !is_string($pending['csrf_token'])
                ) {
                    throw new \Exception('The authorization request is invalid or has expired.', 15403);
                }

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

                $scopeIdentifiers = [];
                foreach ($authorizationRequest->getScopes() as $scope) {
                    $scopeIdentifiers[] = $scope->getIdentifier();
                }
                $requested_access = ScopeCatalog::describe($scopeIdentifiers, Setup::getInstance());
                App::result('request_id', $request_id);
                App::result('csrf_token', $pending['csrf_token']);
                App::result('requested_access', $requested_access);
                App::result('success', true);
            } catch (\Exception $e) {
                // App::result('last_sql', $db->last_sql);
                if ($e->getCode() == 15403) {
                    http_response_code(403);
                }
                App::result('code', $e->getCode());
                App::result('msg', $e->getMessage());
            }
        }, ['get'], true, self::DefaultExpectedFields, self::scope());
    }
}
