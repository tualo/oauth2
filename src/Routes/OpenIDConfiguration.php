<?php

namespace Tualo\Office\OAuth2\Routes;

use Tualo\Office\Basic\Route as BasicRoute;
use Tualo\Office\Basic\TualoApplication as App;

class OpenIDConfiguration extends \Tualo\Office\Basic\RouteWrapper
{
    public static function scope(): string
    {
        return 'basic';
    }
    public static function register()
    {
        BasicRoute::add('/.well-known/openid-configuration', function ($matches) {
            App::contenttype('application/json');
            $setup = \Tualo\Office\OAuth2\Setup::getInstance();
            $config = [
                'issuer' => $setup->env('issuer_url'),
                'authorization_endpoint' => rtrim($setup->env('issuer_url'), '/') . '/oauth2/authorize',
                'token_endpoint' => rtrim($setup->env('issuer_url'), '/') . '/oauth2/token',
                'jwks_uri' => rtrim($setup->env('issuer_url'), '/') . '/oauth2/jwks',
                'response_types_supported' => ['code'],
                'subject_types_supported' => ['public'],
                'id_token_signing_alg_values_supported' => ['RS256'],
                'scopes_supported' => ['openid', 'profile', 'offline_access'],
                'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post'],
                'claims_supported' => ['sub', 'iss', 'aud', 'exp', 'iat', 'name', 'preferred_username'],
            ];
            App::body(json_encode($config));
        }, ['get'], false, [], self::scope());
    }
}
