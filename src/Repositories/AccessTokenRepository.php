<?php

declare(strict_types=1);

namespace Tualo\Office\OAuth2\Repositories;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use Tualo\Office\Basic\MYSQL\Database;
use Tualo\Office\OAuth2\Entities\AccessToken;

final class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    public function __construct(private readonly Database $database)
    {
    }

    public function getNewToken(
        ClientEntityInterface $clientEntity,
        array $scopes,
        ?string $userIdentifier = null
    ): AccessTokenEntityInterface {
        $token = new AccessToken();
        $token->setClient($clientEntity);
        if ($userIdentifier !== null) {
            $token->setUserIdentifier($userIdentifier);
        }
        foreach ($scopes as $scope) {
            if (!$scope instanceof ScopeEntityInterface) {
                throw new \InvalidArgumentException('Every access-token scope must be a scope entity.');
            }
            $token->addScope($scope);
        }

        return $token;
    }

    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        $result = $this->database->direct(
            'INSERT INTO oauth2_access_tokens '
                . '(token_id, client_id, subject_id, scopes, expires_at, is_revoked) '
                . 'VALUES ({token_id}, {client_id}, {subject_id}, {scopes}, {expires_at}, 0)',
            [
                'token_id' => $accessTokenEntity->getIdentifier(),
                'client_id' => $accessTokenEntity->getClient()->getIdentifier(),
                'subject_id' => $accessTokenEntity->getUserIdentifier(),
                'scopes' => $this->encodeScopes($accessTokenEntity->getScopes()),
                'expires_at' => $this->formatExpiry($accessTokenEntity->getExpiryDateTime()),
            ]
        );
        if ($result === false) {
            throw new \RuntimeException('Unable to persist the OAuth access token.');
        }
    }

    public function revokeAccessToken(string $tokenId): void
    {
        $result = $this->database->direct(
            'UPDATE oauth2_access_tokens SET is_revoked = 1 WHERE token_id = {token_id}',
            ['token_id' => $tokenId]
        );
        if ($result === false) {
            throw new \RuntimeException('Unable to revoke the OAuth access token.');
        }
    }

    public function isAccessTokenRevoked(string $tokenId): bool
    {
        $rows = $this->database->direct(
            'SELECT is_revoked, expires_at <= UTC_TIMESTAMP() AS is_expired '
                . 'FROM oauth2_access_tokens WHERE token_id = {token_id}',
            ['token_id' => $tokenId]
        );
        if ($rows === false) {
            throw new \RuntimeException('Unable to check OAuth access-token status.');
        }
        if (!isset($rows[0])) {
            return true;
        }

        return (bool) $rows[0]['is_revoked'] || (bool) $rows[0]['is_expired'];
    }

    private function encodeScopes(array $scopes): string
    {
        return json_encode(
            array_map(static fn (ScopeEntityInterface $scope): string => $scope->getIdentifier(), $scopes),
            JSON_THROW_ON_ERROR
        );
    }

    private function formatExpiry(\DateTimeImmutable $expiry): string
    {
        return $expiry->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
