<?php

declare(strict_types=1);

namespace Tualo\Office\OAuth2\Grants;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Grant\DeviceCodeGrant;

final class OfflineAccessDeviceCodeGrant extends DeviceCodeGrant
{
    protected function issueRefreshToken(
        AccessTokenEntityInterface $accessToken
    ): ?RefreshTokenEntityInterface {
        if (!$this->hasOfflineAccess($accessToken)) {
            return null;
        }

        return parent::issueRefreshToken($accessToken);
    }

    private function hasOfflineAccess(AccessTokenEntityInterface $accessToken): bool
    {
        foreach ($accessToken->getScopes() as $scope) {
            if ($scope->getIdentifier() === 'offline_access') {
                return true;
            }
        }

        return false;
    }
}
