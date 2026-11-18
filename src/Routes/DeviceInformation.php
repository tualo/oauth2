<?php

namespace Tualo\Office\OAuth2\Routes;

use Tualo\Office\Basic\MYSQL\Database;
use Tualo\Office\Basic\Route as BasicRoute;
use Tualo\Office\Basic\Session;
use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\OAuth2\Repositories\ClientRepository;
use Tualo\Office\OAuth2\Repositories\DeviceCodeRepository;
use Tualo\Office\OAuth2\Scopes\OAuth2ConsentInformationScope;
use Tualo\Office\OAuth2\Services\ScopeCatalog;
use Tualo\Office\OAuth2\Setup;

class DeviceInformation extends \Tualo\Office\Basic\RouteWrapper
{
    const DefaultExpectedFields = [
        'user_code' => [
            'required' => true,
            'type' => 'string',
            'minlength' => 0,
            'maxlength' => 100
        ]
    ];

    public static function scope(): string
    {
        return OAuth2ConsentInformationScope::identifier();
    }

    public static function register()
    {
        BasicRoute::add('/oauth2/deviceinformation', function ($matches) {
            App::contenttype('application/json');
            header('Cache-Control: no-store');
            App::result('success', false);
            try {
                $userCode = Device::normalizeUserCode($_GET['user_code'] ?? null);
                if ($userCode === null) {
                    throw new \Exception('A valid user code is required.', 15400);
                }

                $session = App::get('session');
                if (
                    !$session instanceof Session
                    || !$session->isLoggedIn()
                    || !$session->db instanceof Database
                ) {
                    throw new \Exception('An active Tualo session is required.', 15403);
                }

                $database = $session->db;
                $clientRepository = new ClientRepository($database);
                $deviceCodeRepository = new DeviceCodeRepository($database, $clientRepository);
                $pending = $deviceCodeRepository->findPendingByUserCode($userCode);
                if ($pending === null) {
                    throw new \Exception('The device code is invalid, expired, or already decided.', 15404);
                }

                $client = $clientRepository->getClientEntity($pending['client_id']);
                if ($client === null) {
                    throw new \RuntimeException('The OAuth device-code client is unavailable.');
                }

                App::result('user_code', $userCode);
                App::result('client_name', $client->getName());
                App::result('requested_access', ScopeCatalog::describe($pending['scopes'], Setup::getInstance()));
                App::result('success', true);
            } catch (\Exception $e) {
                if ($e->getCode() == 15400) {
                    http_response_code(400);
                } elseif ($e->getCode() == 15403) {
                    http_response_code(403);
                } elseif ($e->getCode() == 15404) {
                    http_response_code(404);
                }
                App::result('code', $e->getCode());
                App::result('msg', $e->getMessage());
            }
        }, ['get'], true, self::DefaultExpectedFields, self::scope());
    }
}
