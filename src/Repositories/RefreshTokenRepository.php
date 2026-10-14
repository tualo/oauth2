<?php

declare(strict_types=1);

namespace Tualo\Office\OAuth2\Repositories;

use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Tualo\Office\Basic\MYSQL\Database;
use Tualo\Office\OAuth2\Entities\RefreshToken;

final class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function __construct(private readonly Database $database)
    {
    }

    public function getNewRefreshToken(): ?RefreshTokenEntityInterface
    {
        return new RefreshToken();
    }

    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        $result = $this->database->direct(
            'INSERT INTO oauth2_refresh_tokens '
                . '(refresh_token_id, access_token_id, expires_at, is_revoked) '
                . 'VALUES ({refresh_token_id}, {access_token_id}, {expires_at}, 0)',
            [
                'refresh_token_id' => $refreshTokenEntity->getIdentifier(),
                'access_token_id' => $refreshTokenEntity->getAccessToken()->getIdentifier(),
                'expires_at' => $this->formatExpiry($refreshTokenEntity->getExpiryDateTime()),
            ]
        );
        if ($result === false) {
            throw new \RuntimeException('Unable to persist the OAuth refresh token.');
        }
    }

    public function revokeRefreshToken(string $tokenId): void
    {
        $result = $this->database->direct(
            'UPDATE oauth2_refresh_tokens SET is_revoked = 1 WHERE refresh_token_id = {token_id}',
            ['token_id' => $tokenId]
        );
        if ($result === false) {
            throw new \RuntimeException('Unable to revoke the OAuth refresh token.');
        }
    }

    public function isRefreshTokenRevoked(string $tokenId): bool
    {
        $rows = $this->database->direct(
            'SELECT is_revoked, expires_at <= UTC_TIMESTAMP() AS is_expired '
                . 'FROM oauth2_refresh_tokens WHERE refresh_token_id = {token_id}',
            ['token_id' => $tokenId]
        );
        if ($rows === false) {
            throw new \RuntimeException('Unable to check OAuth refresh-token status.');
        }
        if (!isset($rows[0])) {
            return true;
        }

        return (bool) $rows[0]['is_revoked'] || (bool) $rows[0]['is_expired'];
    }

    private function formatExpiry(\DateTimeImmutable $expiry): string
    {
        return $expiry->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
