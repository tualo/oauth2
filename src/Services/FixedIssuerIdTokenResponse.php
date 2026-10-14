<?php

declare(strict_types=1);

namespace Tualo\Office\OAuth2\Services;

use DateTimeImmutable;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Builder;
use OpenIDConnectServer\ClaimExtractor;
use OpenIDConnectServer\IdTokenResponse;
use OpenIDConnectServer\Repositories\IdentityProviderInterface;

final class FixedIssuerIdTokenResponse extends IdTokenResponse
{
    public function __construct(
        IdentityProviderInterface $identityProvider,
        ClaimExtractor $claimExtractor,
        private readonly string $issuer,
        ?string $keyIdentifier = null
    ) {
        $issuerParts = parse_url($issuer);
        if (
            $issuerParts === false
            || ($issuerParts['scheme'] ?? '') !== 'https'
            || empty($issuerParts['host'])
            || isset($issuerParts['query'])
            || isset($issuerParts['fragment'])
        ) {
            throw new \InvalidArgumentException('The OIDC issuer must be a fixed HTTPS URL without query or fragment.');
        }

        parent::__construct($identityProvider, $claimExtractor, $keyIdentifier);
    }

    protected function getBuilder(
        AccessTokenEntityInterface $accessToken,
        UserEntityInterface $userEntity
    ): Builder {
        return (new Builder(new JoseEncoder(), ChainedFormatter::withUnixTimestampDates()))
            ->permittedFor($accessToken->getClient()->getIdentifier())
            ->issuedBy($this->issuer)
            ->issuedAt(new DateTimeImmutable())
            ->expiresAt($accessToken->getExpiryDateTime())
            ->relatedTo($userEntity->getIdentifier());
    }
}
