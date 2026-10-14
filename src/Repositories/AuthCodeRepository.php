<?php

declare(strict_types=1);

namespace Tualo\Office\OAuth2\Repositories;

use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use Tualo\Office\Basic\MYSQL\Database;
use Tualo\Office\OAuth2\Entities\AuthorizationCode;

final class AuthCodeRepository implements AuthCodeRepositoryInterface
{
    public function __construct(private readonly Database $database)
    {
    }

    public function getNewAuthCode(): AuthCodeEntityInterface
    {
        return new AuthorizationCode();
    }

    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        $result = $this->database->direct(
            'INSERT INTO oauth2_authorization_codes '
                . '(code_id, client_id, subject_id, redirect_uri, scopes, expires_at, is_revoked) '
                . 'VALUES ({code_id}, {client_id}, {subject_id}, {redirect_uri}, {scopes}, {expires_at}, 0)',
            [
                'code_id' => $authCodeEntity->getIdentifier(),
                'client_id' => $authCodeEntity->getClient()->getIdentifier(),
                'subject_id' => $authCodeEntity->getUserIdentifier(),
                'redirect_uri' => $authCodeEntity->getRedirectUri(),
                'scopes' => $this->encodeScopes($authCodeEntity->getScopes()),
                'expires_at' => $this->formatExpiry($authCodeEntity->getExpiryDateTime()),
            ]
        );

        if ($result === false) {
            throw new \RuntimeException('Unable to persist the OAuth authorization code.');
        }
    }

    public function revokeAuthCode(string $codeId): void
    {
        $result = $this->database->direct(
            'UPDATE oauth2_authorization_codes SET is_revoked = 1 WHERE code_id = {code_id}',
            ['code_id' => $codeId]
        );
        if ($result === false) {
            throw new \RuntimeException('Unable to revoke the OAuth authorization code.');
        }
    }

    public function isAuthCodeRevoked(string $codeId): bool
    {
        return $this->isRevoked(
            'oauth2_authorization_codes',
            'code_id',
            $codeId
        );
    }

    private function isRevoked(string $table, string $idColumn, string $identifier): bool
    {
        $rows = $this->database->direct(
            "SELECT is_revoked, expires_at <= UTC_TIMESTAMP() AS is_expired FROM {$table} WHERE {$idColumn} = {identifier}",
            ['identifier' => $identifier]
        );
        if ($rows === false) {
            throw new \RuntimeException('Unable to check OAuth authorization-code status.');
        }
        if (!isset($rows[0])) {
            return true;
        }

        return (bool) $rows[0]['is_revoked'] || (bool) $rows[0]['is_expired'];
    }

    private function encodeScopes(array $scopes): string
    {
        return json_encode(
            array_map(static fn ($scope): string => $scope->getIdentifier(), $scopes),
            JSON_THROW_ON_ERROR
        );
    }

    private function formatExpiry(\DateTimeImmutable $expiry): string
    {
        return $expiry->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
