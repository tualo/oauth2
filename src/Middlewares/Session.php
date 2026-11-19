<?php

namespace Tualo\Office\Oauth2\Middlewares;

use GuzzleHttp\Psr7\ServerRequest;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\Basic\IMiddleware;
use Tualo\Office\OAuth2\Repositories\AccessTokenRepository;

class Session implements IMiddleware
{
    /**
     * Resolves the public key used to verify access-token signatures.
     * Uses oauth2.public_key_path when configured, otherwise derives the
     * public key from oauth2.private_key_path.
     */
    private static function publicKey(): CryptKey
    {
        $publicKeyPath = App::configuration('oauth2', 'public_key_path', false);
        if (is_string($publicKeyPath) && trim($publicKeyPath) !== '') {
            return new CryptKey('file://' . $publicKeyPath, null, false);
        }

        $privateKeyPath = App::configuration('oauth2', 'private_key_path', false);
        if (!is_string($privateKeyPath) || trim($privateKeyPath) === '') {
            throw new \RuntimeException('OAuth configuration "private_key_path" is required.');
        }
        $passphrase = App::configuration('oauth2', 'private_key_passphrase', null);

        $private = openssl_pkey_get_private('file://' . $privateKeyPath, is_string($passphrase) ? $passphrase : '');
        if ($private === false) {
            throw new \RuntimeException('Unable to load the OAuth private key.');
        }
        $details = openssl_pkey_get_details($private);
        if ($details === false || !isset($details['key'])) {
            throw new \RuntimeException('Unable to derive the OAuth public key.');
        }

        return new CryptKey($details['key'], null, false);
    }

    /**
     * Narrows the tualo user scopes down to what the access token allows.
     * The result never exceeds the user's own permissions: for every token
     * scope the narrower of token scope and user scope wins
     * (user "ds.read" + token "ds.read:ds" => "ds.read:ds",
     *  user "ds.read:ds" + token "ds.read" => "ds.read:ds").
     *
     * @param string[] $userScopes
     * @param string[] $tokenScopes
     * @return string[]
     */
    public static function narrowScopes(array $userScopes, array $tokenScopes): array
    {
        $effective = [];
        foreach ($tokenScopes as $tokenScope) {
            foreach ($userScopes as $userScope) {
                if ($tokenScope === $userScope || str_starts_with($tokenScope, $userScope . ':')) {
                    $effective[] = $tokenScope;
                } elseif (str_starts_with($userScope, $tokenScope . ':')) {
                    $effective[] = $userScope;
                }
            }
        }

        return array_values(array_unique($effective));
    }

    public static function register()
    {
        // bsc session middlewares run at: instance -9000000, logout -8999999,
        // login -8999998, auth -8999997, temp -8999996. This middleware needs
        // the session instance and must win over the (failing) bearer handling
        // of bsc auth.php, so it runs right after them.
        $middlewareOrder = -8999995;

        App::use('Oauth2SessionInit', function () {
            try {
                $session = App::get('session');
                if (empty($session) || is_null($session->db)) {
                    return;
                }

                $authToken = $session->getHeader('Authorization');
                if ($authToken === false) {
                    return;
                }
                if (strpos($authToken, 'Bearer ') === 0) {
                    $authToken = substr($authToken, strlen('Bearer '));
                }
                $authToken = trim($authToken);

                // only handle JWT access tokens (header.payload.signature),
                // classic tualo tokens are handled by bsc auth.php
                if ($authToken === '' || count(explode('.', $authToken)) !== 3) {
                    return;
                }

                if (
                    isset($_SESSION['tualoapplication']['loggedIn'])
                    && $_SESSION['tualoapplication']['loggedIn'] === true
                ) {
                    return;
                }

                $resourceServer = new ResourceServer(
                    new AccessTokenRepository($session->db),
                    self::publicKey()
                );

                try {
                    $request = $resourceServer->validateAuthenticatedRequest(
                        ServerRequest::fromGlobals()
                    );
                } catch (OAuthServerException $e) {
                    App::logger('Oauth2')->warning('Access token rejected: ' . $e->getMessage());
                    App::result('success', false);
                    App::result('msg', 'Anmeldung fehlerhaft');
                    return;
                }

                $subjectId = $request->getAttribute('oauth_user_id');
                if (!is_string($subjectId) || $subjectId === '') {
                    // e.g. client_credentials tokens carry no user subject
                    App::logger('Oauth2')->warning('Access token without user subject, no session created');
                    return;
                }

                $row = $session->db->singleRow(
                    'select
                        macc_users_clients.client db_name,
                        concat(loginnamen.vorname, \' \', loginnamen.nachname) fullname,
                        view_macc_clients.username db_user,
                        view_macc_clients.password db_pass,
                        view_macc_clients.host db_host,
                        view_macc_clients.port db_port,
                        macc_users.typ,
                        macc_users.login login
                    from
                        oauth2_subjects
                        join macc_users_clients on oauth2_subjects.login = macc_users_clients.login
                        join view_macc_clients on macc_users_clients.client = view_macc_clients.id
                        join loginnamen on oauth2_subjects.login = loginnamen.login
                        join macc_users on macc_users.login = loginnamen.login
                    where
                        oauth2_subjects.subject_id = {subject_id}',
                    ['subject_id' => $subjectId]
                );

                if ($row === false) {
                    App::logger('Oauth2')->error('No tualo user found for subject ' . $subjectId);
                    App::result('success', false);
                    App::result('msg', 'Anmeldung fehlerhaft');
                    return;
                }

                $_SESSION['session_condition'] = [];
                $_SESSION['tualoapplication']['loggedInType'] = 'oauth2';
                $_SESSION['tualoapplication']['loggedIn'] = true;
                $_SESSION['tualoapplication']['typ'] = $row['typ'];
                $_SESSION['tualoapplication']['username'] = $row['login'];
                $_SESSION['tualoapplication']['fullname'] = $row['fullname'];
                $_SESSION['tualoapplication']['client'] = $row['db_name'];
                $_SESSION['tualoapplication']['clients'] = $session->db->direct(
                    'SELECT macc_users_clients.client FROM macc_users_clients '
                        . 'JOIN view_macc_clients ON macc_users_clients.client = view_macc_clients.id '
                        . 'WHERE macc_users_clients.login = {username}',
                    $_SESSION['tualoapplication']
                );
                $_SESSION['tualoapplication']['oauth2'] = [
                    'access_token_id' => $request->getAttribute('oauth_access_token_id'),
                    'client_id' => $request->getAttribute('oauth_client_id'),
                    'subject_id' => $subjectId,
                    'scopes' => $request->getAttribute('oauth_scopes', []),
                ];

                // wichtig: speichere die OAuth2-Scopes in der Session
                $_SESSION['tualoapplication']['scopes'] = $request->getAttribute('oauth_scopes', []);

                $_SESSION['db']['db_host'] = $row['db_host'];
                $_SESSION['db']['db_user'] = $row['db_user'];
                $_SESSION['db']['db_pass'] = $row['db_pass'];
                $_SESSION['db']['db_port'] = $row['db_port'];
                $_SESSION['db']['db_name'] = $row['db_name'];

                if (is_null($session->getDB())) {
                    App::logger('Oauth2')->error('Client database not reachable for ' . $row['login']);
                    App::result('success', false);
                    App::result('msg', 'Fehler beim Zugriff auf die Datenbank');
                    $session->destroy();
                    return;
                }

                // tokens carrying API scopes limit the session to exactly
                // those scopes (intersected with the user's own permissions);
                // pure OIDC login tokens keep the full user permissions
                $tokenScopes = $_SESSION['tualoapplication']['oauth2']['scopes'];
                $apiScopes = array_values(array_diff(
                    is_array($tokenScopes) ? $tokenScopes : [],
                    ['openid', 'profile', 'offline_access']
                ));
                if ($apiScopes !== []) {
                    unset($_SESSION['tualoapplication']['scopes']);
                    $_SESSION['tualoapplication']['scopes'] = self::narrowScopes(
                        $session->getUserScopes(),
                        $apiScopes
                    );
                }

                App::result('fullname', $_SESSION['tualoapplication']['fullname']);
                App::result('username', $_SESSION['tualoapplication']['username']);
                App::result('client', $_SESSION['tualoapplication']['client']);
                App::result('clients', $_SESSION['tualoapplication']['clients']);
                App::result('dbaccess', true);
                App::result('success', true);
                App::result('msg', 'Login OK');
                App::logger('Oauth2')->debug('Login via access token: ' . $row['login']);

                header(
                    'Access-Control-Allow-Origin: '
                        . App::configuration('oauth2', 'access_control_allow_origin', '*')
                );
                session_commit();
            } catch (\Throwable $e) {
                App::logger('Oauth2')->error('OAuth2 session middleware failed: ' . $e->getMessage());
                App::result('success', false);
                App::result('msg', 'Anmeldung fehlerhaft');
            }
        }, $middlewareOrder, [], true);
    }
}
