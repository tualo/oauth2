<?php

declare(strict_types=1);

namespace Tualo\Office\OAuth2\Entities;

use League\OAuth2\Server\Entities\UserEntityInterface;
use OpenIDConnectServer\Entities\ClaimSetInterface;

final class OAuthUser implements UserEntityInterface, ClaimSetInterface
{
    private array $claims;

    public function __construct(
        private readonly string $identifier,
        string $username,
        string $displayName
    ) {
        if ($identifier === '') {
            throw new \InvalidArgumentException('The OIDC subject identifier must not be empty.');
        }

        if ($username === '') {
            throw new \InvalidArgumentException('The username claim must not be empty.');
        }

        $this->claims = [
            'preferred_username' => $username,
            'name' => $displayName !== '' ? $displayName : $username,
        ];
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getClaims(): array
    {
        return $this->claims;
    }
}
