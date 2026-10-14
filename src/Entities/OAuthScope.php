<?php

declare(strict_types=1);

namespace Tualo\Office\OAuth2\Entities;

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\ScopeTrait;

final class OAuthScope implements ScopeEntityInterface
{
    use EntityTrait;
    use ScopeTrait;

    public function __construct(string $identifier)
    {
        if ($identifier === '') {
            throw new \InvalidArgumentException('The scope identifier must not be empty.');
        }

        $this->setIdentifier($identifier);
    }
}
