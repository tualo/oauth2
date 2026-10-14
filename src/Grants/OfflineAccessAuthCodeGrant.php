<?php

declare(strict_types=1);

namespace Tualo\Office\OAuth2\Grants;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use Psr\Http\Message\ServerRequestInterface;

final class OfflineAccessAuthCodeGrant extends AuthCodeGrant
{
    public function validateAuthorizationRequest(ServerRequestInterface $request): AuthorizationRequestInterface
    {
        $query = $request->getQueryParams();
        if (
            !isset($query['code_challenge'])
            || !is_string($query['code_challenge'])
            || !isset($query['code_challenge_method'])
            || $query['code_challenge_method'] !== 'S256'
        ) {
            throw OAuthServerException::invalidRequest(
                'code_challenge',
                'Authorization code requests must use PKCE with the S256 method.'
            );
        }

        return parent::validateAuthorizationRequest($request);
    }

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
