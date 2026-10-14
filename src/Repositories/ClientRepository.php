<?php

declare(strict_types=1);

namespace Tualo\Office\OAuth2\Repositories;

use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Tualo\Office\Basic\MYSQL\Database;
use Tualo\Office\OAuth2\Entities\OAuthClient;

final class ClientRepository implements ClientRepositoryInterface
{
    public function __construct(private readonly Database $database)
    {
    }

    public function getClientEntity(string $clientIdentifier): ?OAuthClient
    {
        $rows = $this->database->direct(
            'SELECT client_id, name, redirect_uris, is_confidential, grant_types, allowed_scopes, secret_hash '
                . 'FROM oauth2_clients WHERE client_id = {client_id} AND is_active = 1',
            ['client_id' => $clientIdentifier]
        );
        if ($rows === false) {
            throw new \RuntimeException('Unable to load the OAuth client.');
        }
        if (!isset($rows[0])) {
            return null;
        }

        $row = $rows[0];

        return new OAuthClient(
            (string) $row['client_id'],
            (string) $row['name'],
            $this->decodeStringList($row['redirect_uris'], 'redirect URIs'),
            (bool) $row['is_confidential'],
            $this->decodeStringList($row['grant_types'], 'grant types'),
            $this->decodeStringList($row['allowed_scopes'], 'allowed scopes'),
            isset($row['secret_hash']) ? (string) $row['secret_hash'] : null
        );
    }

    public function validateClient(
        string $clientIdentifier,
        ?string $clientSecret,
        ?string $grantType
    ): bool {
        $client = $this->getClientEntity($clientIdentifier);
        if ($client === null) {
            return false;
        }

        if ($grantType !== null && !$client->supportsGrantType($grantType)) {
            return false;
        }

        if ($client->isConfidential()) {
            return $client->verifiesSecret($clientSecret);
        }

        return $clientSecret === null || $clientSecret === '';
    }

    private function decodeStringList(mixed $value, string $field): array
    {
        try {
            $decoded = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \UnexpectedValueException(
                sprintf('The OAuth client %s must contain a valid JSON list.', $field),
                previous: $exception
            );
        }

        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw new \UnexpectedValueException(
                sprintf('The OAuth client %s must contain a JSON list.', $field)
            );
        }

        foreach ($decoded as $item) {
            if (!is_string($item) || $item === '') {
                throw new \UnexpectedValueException(
                    sprintf('The OAuth client %s may only contain non-empty strings.', $field)
                );
            }
        }

        return $decoded;
    }
}
