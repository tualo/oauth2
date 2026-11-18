<?php

declare(strict_types=1);

namespace Tualo\Office\OAuth2\Entities;

use League\OAuth2\Server\Entities\DeviceCodeEntityInterface;
use League\OAuth2\Server\Entities\Traits\DeviceCodeTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;

final class DeviceCode implements DeviceCodeEntityInterface
{
    use EntityTrait;
    use DeviceCodeTrait;
    use TokenEntityTrait;
}
