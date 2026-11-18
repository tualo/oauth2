<?php

namespace Tualo\Office\OAuth2\Scopes;

use Tualo\Office\Basic\Scope;

class OAuth2UIScope extends Scope
{
    protected static string $identifier = 'oauth2.ui';
    protected static array $name = [
        'EN' => 'OAuth2 UI',
        'DE' => 'OAuth2-Benutzeroberfläche',
    ];
    protected static array $description = [
        'EN' => 'Access and manage the OAuth2 user interface.',
        'DE' => 'Auf die OAuth2-Benutzeroberfläche zugreifen und sie verwalten.',
    ];
}
