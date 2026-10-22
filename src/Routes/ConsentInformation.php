<?php

namespace Tualo\Office\OAuth2\Routes;

use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use Tualo\Office\Basic\Route as BasicRoute;
use Tualo\Office\Basic\Session;
use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\OAuth2\Entities\OAuthUser;
use Tualo\Office\OAuth2\Scopes\OAuth2ConsentInformationScope;

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
        BasicRoute::add('/consentinformation', function ($matches) {
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
                    || !isset($pending['expires_at'], $pending['authorization_request'])
                    || !is_int($pending['expires_at'])
                    || $pending['expires_at'] <= time()
                    || !($pending['authorization_request'] instanceof AuthorizationRequestInterface)
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

                $scopeDescriptions = [
                    'openid' => [
                        'name' => 'Anmeldung',
                        'description' => 'Dich anhand einer eindeutigen Benutzerkennung erkennen.',
                    ],
                    'profile' => [
                        'name' => 'Profil',
                        'description' => 'Deinen Benutzernamen und Anzeigenamen lesen.',
                    ],
                    'offline_access' => [
                        'name' => 'Dauerhafter Zugriff',
                        'description' => 'Die Zugriffsberechtigung erneuern, auch wenn du nicht mehr aktiv angemeldet bist.',
                    ],
                ];
                $requested_access = [];
                foreach ($authorizationRequest->getScopes() as $scope) {
                    $identifier = $scope->getIdentifier();
                    $description = $scopeDescriptions[$identifier] ?? [
                        'name' => $identifier,
                        'description' => 'Anwendungsspezifische Berechtigung: ' . $identifier,
                    ];
                    $requested_access[] = [
                        'type' => 'string',
                        'id' => $identifier,
                        'name' => $description['name'],
                        'description' => $description['description'],
                    ];
                }
                App::result('request_id', $request_id);
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
