<?php

declare(strict_types=1);

namespace Tualo\Office\OAuth2\Repositories;

use League\OAuth2\Server\Entities\DeviceCodeEntityInterface;
use League\OAuth2\Server\Repositories\DeviceCodeRepositoryInterface;
use Tualo\Office\Basic\MYSQL\Database;
use Tualo\Office\OAuth2\Entities\DeviceCode;
use Tualo\Office\OAuth2\Entities\OAuthScope;

final class DeviceCodeRepository implements DeviceCodeRepositoryInterface
{
    public function __construct(
        private readonly Database $database,
        private readonly ClientRepository $clientRepository
    ) {
    }

    public function getNewDeviceCode(): DeviceCodeEntityInterface
    {
        return new DeviceCode();
    }

    public function persistDeviceCode(DeviceCodeEntityInterface $deviceCodeEntity): void
    {
        $result = $this->database->direct(
            'INSERT INTO oauth2_device_codes '
                . '(device_code_id, user_code, client_id, subject_id, scopes, verification_uri, '
                . 'user_approved, retry_interval, last_polled_at, expires_at, is_revoked) '
                . 'VALUES ({device_code_id}, {user_code}, {client_id}, {subject_id}, {scopes}, '
                . '{verification_uri}, {user_approved}, {retry_interval}, {last_polled_at}, {expires_at}, 0) '
                . 'ON DUPLICATE KEY UPDATE '
                . 'subject_id = VALUES(subject_id), '
                . 'user_approved = VALUES(user_approved), '
                . 'last_polled_at = VALUES(last_polled_at)',
            [
                'device_code_id' => $deviceCodeEntity->getIdentifier(),
                'user_code' => $deviceCodeEntity->getUserCode(),
                'client_id' => $deviceCodeEntity->getClient()->getIdentifier(),
                'subject_id' => $deviceCodeEntity->getUserIdentifier(),
                'scopes' => $this->encodeScopes($deviceCodeEntity->getScopes()),
                'verification_uri' => $deviceCodeEntity->getVerificationUri(),
                'user_approved' => $deviceCodeEntity->getUserApproved() ? 1 : 0,
                'retry_interval' => $deviceCodeEntity->getInterval(),
                'last_polled_at' => $deviceCodeEntity->getLastPolledAt() === null
                    ? null
                    : $this->formatDateTime($deviceCodeEntity->getLastPolledAt()),
                'expires_at' => $this->formatDateTime($deviceCodeEntity->getExpiryDateTime()),
            ]
        );

        if ($result === false) {
            throw new \RuntimeException('Unable to persist the OAuth device code.');
        }
    }

    public function getDeviceCodeEntityByDeviceCode(string $deviceCodeEntity): ?DeviceCodeEntityInterface
    {
        $rows = $this->database->direct(
            'SELECT device_code_id, user_code, client_id, subject_id, scopes, verification_uri, '
                . 'user_approved, retry_interval, last_polled_at, expires_at '
                . 'FROM oauth2_device_codes WHERE device_code_id = {device_code_id}',
            ['device_code_id' => $deviceCodeEntity]
        );
        if ($rows === false) {
            throw new \RuntimeException('Unable to load the OAuth device code.');
        }
        if (!isset($rows[0])) {
            return null;
        }

        return $this->hydrate($rows[0]);
    }

    public function revokeDeviceCode(string $codeId): void
    {
        $result = $this->database->direct(
            'UPDATE oauth2_device_codes SET is_revoked = 1 WHERE device_code_id = {device_code_id}',
            ['device_code_id' => $codeId]
        );
        if ($result === false) {
            throw new \RuntimeException('Unable to revoke the OAuth device code.');
        }
    }

    public function isDeviceCodeRevoked(string $codeId): bool
    {
        $rows = $this->database->direct(
            'SELECT is_revoked, expires_at <= UTC_TIMESTAMP() AS is_expired '
                . 'FROM oauth2_device_codes WHERE device_code_id = {device_code_id}',
            ['device_code_id' => $codeId]
        );
        if ($rows === false) {
            throw new \RuntimeException('Unable to check OAuth device-code status.');
        }
        if (!isset($rows[0])) {
            return true;
        }

        return (bool) $rows[0]['is_revoked'] || (bool) $rows[0]['is_expired'];
    }

    /**
     * Looks up a pending (undecided, unexpired) device authorization by its
     * user code for the verification UI. Returns the device code identifier,
     * client id, and requested scope identifiers, or null.
     */
    public function findPendingByUserCode(string $userCode): ?array
    {
        $rows = $this->database->direct(
            'SELECT device_code_id, client_id, scopes FROM oauth2_device_codes '
                . 'WHERE user_code = {user_code} AND is_revoked = 0 '
                . 'AND subject_id IS NULL AND expires_at > UTC_TIMESTAMP()',
            ['user_code' => $userCode]
        );
        if ($rows === false) {
            throw new \RuntimeException('Unable to look up the OAuth device code.');
        }
        if (!isset($rows[0])) {
            return null;
        }

        $scopes = json_decode((string) $rows[0]['scopes'], true);
        if (!is_array($scopes)) {
            throw new \UnexpectedValueException('The OAuth device code scopes are malformed.');
        }

        return [
            'device_code_id' => (string) $rows[0]['device_code_id'],
            'client_id' => (string) $rows[0]['client_id'],
            'scopes' => $scopes,
        ];
    }

    private function hydrate(array $row): DeviceCode
    {
        $client = $this->clientRepository->getClientEntity((string) $row['client_id']);
        if ($client === null) {
            throw new \RuntimeException('The OAuth device-code client is unavailable.');
        }

        $scopeIdentifiers = json_decode((string) $row['scopes'], true);
        if (!is_array($scopeIdentifiers)) {
            throw new \UnexpectedValueException('The OAuth device code scopes are malformed.');
        }

        $deviceCode = new DeviceCode();
        $deviceCode->setIdentifier((string) $row['device_code_id']);
        $deviceCode->setUserCode((string) $row['user_code']);
        $deviceCode->setClient($client);
        $deviceCode->setVerificationUri((string) $row['verification_uri']);
        $deviceCode->setUserApproved((bool) $row['user_approved']);
        $deviceCode->setInterval((int) $row['retry_interval']);
        $deviceCode->setExpiryDateTime($this->parseDateTime((string) $row['expires_at']));
        if ($row['last_polled_at'] !== null) {
            $deviceCode->setLastPolledAt($this->parseDateTime((string) $row['last_polled_at']));
        }
        if ($row['subject_id'] !== null && $row['subject_id'] !== '') {
            $deviceCode->setUserIdentifier((string) $row['subject_id']);
        }
        foreach ($scopeIdentifiers as $identifier) {
            if (!is_string($identifier) || $identifier === '') {
                throw new \UnexpectedValueException('The OAuth device code scopes are malformed.');
            }
            $deviceCode->addScope(new OAuthScope($identifier));
        }

        return $deviceCode;
    }

    private function encodeScopes(array $scopes): string
    {
        return json_encode(
            array_map(static fn ($scope): string => $scope->getIdentifier(), $scopes),
            JSON_THROW_ON_ERROR
        );
    }

    private function formatDateTime(\DateTimeImmutable $dateTime): string
    {
        return $dateTime->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function parseDateTime(string $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
    }
}
