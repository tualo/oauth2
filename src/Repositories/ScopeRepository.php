<?php

declare(strict_types=1);

namespace Tualo\Office\OAuth2\Repositories;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Tualo\Office\OAuth2\Entities\OAuthClient;
use Tualo\Office\OAuth2\Entities\OAuthScope;

final class ScopeRepository implements ScopeRepositoryInterface
{
    public function getScopeEntityByIdentifier(string $identifier): ?ScopeEntityInterface
    {
        if (!preg_match('/\A[A-Za-z0-9][A-Za-z0-9:._-]{0,127}\z/', $identifier)) {
            return null;
        }

        return new OAuthScope($identifier);
    }

    public function finalizeScopes(
        array $scopes,
        string $grantType,
        ClientEntityInterface $clientEntity,
        ?string $userIdentifier = null,
        ?string $authCodeId = null
    ): array {
        if (!$clientEntity instanceof OAuthClient) {
            throw new \InvalidArgumentException('Unsupported OAuth client entity.');
        }

        $scopeIdentifiers = [];
        foreach ($scopes as $scope) {
            if (!$scope instanceof ScopeEntityInterface) {
                throw new \InvalidArgumentException('Every requested scope must be a scope entity.');
            }

            $identifier = $scope->getIdentifier();
            if (!in_array($identifier, $clientEntity->getAllowedScopes(), true)) {
                throw OAuthServerException::invalidScope($identifier);
            }
            $scopeIdentifiers[] = $identifier;
        }

        $oidcScopes = ['openid', 'profile', 'offline_access'];
        if ($grantType === 'client_credentials' && array_intersect($scopeIdentifiers, $oidcScopes) !== []) {
            throw OAuthServerException::invalidScope('openid');
        }

        if (in_array('profile', $scopeIdentifiers, true) && !in_array('openid', $scopeIdentifiers, true)) {
            throw OAuthServerException::invalidScope('profile');
        }

        if (in_array('offline_access', $scopeIdentifiers, true)) {
            if (!in_array('openid', $scopeIdentifiers, true)) {
                throw OAuthServerException::invalidScope('offline_access');
            }
            if (!$clientEntity->supportsGrantType('refresh_token')) {
                throw OAuthServerException::invalidScope('offline_access');
            }
        }

        return $scopes;
    }
}
