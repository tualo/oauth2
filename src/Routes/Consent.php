<?php

namespace Tualo\Office\OAuth2\Routes;

use Tualo\Office\Basic\Route as BasicRoute;
use Tualo\Office\Basic\TualoApplication as App;

class Consent extends \Tualo\Office\Basic\RouteWrapper
{
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
    }
}
