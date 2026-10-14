<?php

declare(strict_types=1);

namespace Tualo\Office\OAuth2\Entities;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;

final class OAuthClient implements ClientEntityInterface
{
    use EntityTrait;

    public function __construct(
        string $identifier,
        private readonly string $name,
        private readonly array $redirectUris,
        private readonly bool $confidential,
        private readonly array $grantTypes,
        private readonly array $allowedScopes,
        private readonly ?string $secretHash
    ) {
        if ($identifier === '') {
            throw new \InvalidArgumentException('The client identifier must not be empty.');
        }

        $this->setIdentifier($identifier);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRedirectUri(): array
    {
        return $this->redirectUris;
    }

    public function isConfidential(): bool
    {
        return $this->confidential;
    }

    public function supportsGrantType(string $grantType): bool
    {
        return in_array($grantType, $this->grantTypes, true);
    }

    public function getAllowedScopes(): array
    {
        return $this->allowedScopes;
    }

    public function verifiesSecret(?string $secret): bool
    {
        return $this->confidential
            && $secret !== null
            && $secret !== ''
            && $this->secretHash !== null
            && password_verify($secret, $this->secretHash);
    }
}
