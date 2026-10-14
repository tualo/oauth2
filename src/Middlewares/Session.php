<?php

namespace Tualo\Office\Oauth2\Middlewares;

use Tualo\Office\Basic\TualoApplication;
use Tualo\Office\Basic\IMiddleware;

class Session implements IMiddleware
{
    public static function register()
    {
        // execution order likely auth in bsc
        $middlewareOrder = -9000004;

        TualoApplication::use('Oauth2SessionInit', function () {
            // Initialize the OAuth2 session here
        }, $middlewareOrder, [], true);
    }
}
