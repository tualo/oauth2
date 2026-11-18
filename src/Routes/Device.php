<?php

declare(strict_types=1);

namespace Tualo\Office\OAuth2\Routes;

use Tualo\Office\Basic\MYSQL\Database;
use Tualo\Office\Basic\Route as BasicRoute;
use Tualo\Office\Basic\Session;
use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\OAuth2\Repositories\ClientRepository;
use Tualo\Office\OAuth2\Repositories\DeviceCodeRepository;
use Tualo\Office\OAuth2\Repositories\IdentityRepository;
use Tualo\Office\OAuth2\Services\AuthorizationServerFactory;

final class Device extends \Tualo\Office\Basic\RouteWrapper
{
    public static function scope(): string
    {
        return 'basic';
    }

    public static function normalizeUserCode(mixed $userCode): ?string
    {
        if (!is_string($userCode)) {
            return null;
        }
        $normalized = strtoupper(preg_replace('/[\s-]+/', '', $userCode) ?? '');
        if (!preg_match('/\A[BCDFGHJKLMNPQRSTVWXZ]{8}\z/', $normalized)) {
            return null;
        }

        return $normalized;
    }

    public static function register(): void
    {
        BasicRoute::add('/oauth2/device', static function (): bool {
            $target = '../#oauth2-device';
            $userCode = self::normalizeUserCode($_GET['user_code'] ?? null);
            if ($userCode !== null) {
                $target .= '/' . rawurlencode($userCode);
            }
            header('Location: ' . $target);
            header('Cache-Control: no-store');

            return true;
        }, ['get'], true, [], self::scope());

        BasicRoute::add('/oauth2/device', static function (): bool {
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

                $userCode = self::normalizeUserCode($input['user_code'] ?? null);
                if ($userCode === null) {
                    throw new \Exception('A valid user code is required.', 15400);
                }

                $decision = $input['decision'] ?? null;
                if (!in_array($decision, ['approve', 'deny'], true)) {
                    throw new \Exception('The decision must be "approve" or "deny".', 15400);
                }

                $stage = 'session_validation';
                $session = App::get('session');
                if (
                    !$session instanceof Session
                    || !$session->isLoggedIn()
                    || !$session->db instanceof Database
                ) {
                    throw new \Exception('An active Tualo session is required.', 15403);
                }
                $username = $session->getUsername();
                if (!is_string($username) || $username === '') {
                    throw new \RuntimeException('The authenticated Tualo user is unavailable.');
                }

                $stage = 'device_code_lookup';
                $database = $session->db;
                $deviceCodeRepository = new DeviceCodeRepository($database, new ClientRepository($database));
                $pending = $deviceCodeRepository->findPendingByUserCode($userCode);
                if ($pending === null) {
                    throw new \Exception('The device code is invalid, expired, or already decided.', 15404);
                }

                $stage = 'server_configuration';
                $server = AuthorizationServerFactory::fromTualoApplication($database)->create();

                $stage = 'device_completion';
                $identityRepository = new IdentityRepository($database);
                $subjectId = $identityRepository->getOrCreateSubjectId($username);
                $server->completeDeviceAuthorizationRequest(
                    $pending['device_code_id'],
                    $subjectId,
                    $decision === 'approve'
                );

                App::result('decision', $decision);
                App::result('success', true);
            } catch (\Exception $e) {
                if ($e->getCode() == 15400) {
                    http_response_code(400);
                } elseif ($e->getCode() == 15403) {
                    http_response_code(403);
                } elseif ($e->getCode() == 15404) {
                    http_response_code(404);
                } else {
                    http_response_code(500);
                    App::logger('oauth2')->error('Device verification processing failed.', [
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                        'code' => $e->getCode(),
                        'stage' => $stage,
                        'file' => basename($e->getFile()),
                        'line' => $e->getLine(),
                    ]);
                }
                App::result('code', $e->getCode());
                App::result('msg', in_array($e->getCode(), [15400, 15403, 15404], true)
                    ? $e->getMessage()
                    : 'The device verification could not be processed.');
            }

            return true;
        }, ['post'], true, [], self::scope());
    }
}
