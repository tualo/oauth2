<?php

namespace Tualo\Office\OAuth2\Routes;

use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\OAuth2\Scopes\OAuth2UIScope;
use Tualo\Office\Basic\Route as BasicRoute;
use Tualo\Office\Basic\RouteSecurityHelper;
use Tualo\Office\Basic\IRoute;


class JsLoader extends \Tualo\Office\Basic\RouteWrapper
{

    public static function scope(): string
    {
        return \Tualo\Office\OAuth2\Scopes\OAuth2UIScope::identifier();
    }
    public static function register()
    {
        BasicRoute::add('/jsoauth2/(?P<file>[\w.\/\-]+).js', function ($matches) {
            RouteSecurityHelper::serveSecureStaticFile(
                $matches['file'] . '.js',
                dirname(__DIR__, 1) . '/js/lazy/',
                ['js'],
                ['js' => 'application/javascript']
            );
        }, ['get'], true, [], self::scope());
    }
}
