<?php

declare(strict_types=1);

namespace Tualo\Office\OAuth2\Grants;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Grant\RefreshTokenGrant;

final class OfflineAccessRefreshTokenGrant extends RefreshTokenGrant
{
    protected function issueRefreshToken(
        AccessTokenEntityInterface $accessToken
    ): ?RefreshTokenEntityInterface {
        foreach ($accessToken->getScopes() as $scope) {
            if ($scope->getIdentifier() === 'offline_access') {
                return parent::issueRefreshToken($accessToken);
            }
        }

        return null;
    }
}
