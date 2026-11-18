<?php

namespace Tualo\Office\OAuth2\Scopes;

use Tualo\Office\Basic\Scope;

class OAuth2ConsentInformationScope extends Scope
{
    protected static string $identifier = 'oauth2.consent.information';
    protected static array $name = [
        'EN' => 'OAuth2 Consent Information',
        'DE' => 'OAuth2-Zustimmungsinformationen',
    ];
    protected static array $description = [
        'EN' => 'Access and manage OAuth2 consent information.',
        'DE' => 'OAuth2-Zustimmungsinformationen zugreifen und verwalten.',
    ];
}
