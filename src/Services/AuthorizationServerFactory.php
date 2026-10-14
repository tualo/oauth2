<?php

declare(strict_types=1);

namespace Tualo\Office\OAuth2\Services;

use Defuse\Crypto\Key;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use OpenIDConnectServer\ClaimExtractor;
use Tualo\Office\Basic\MYSQL\Database;
use Tualo\Office\OAuth2\Grants\OfflineAccessAuthCodeGrant;
use Tualo\Office\OAuth2\Grants\OfflineAccessRefreshTokenGrant;
use Tualo\Office\OAuth2\Repositories\AccessTokenRepository;
use Tualo\Office\OAuth2\Repositories\AuthCodeRepository;
use Tualo\Office\OAuth2\Repositories\ClientRepository;
use Tualo\Office\OAuth2\Repositories\IdentityRepository;
use Tualo\Office\OAuth2\Repositories\RefreshTokenRepository;
use Tualo\Office\OAuth2\Repositories\ScopeRepository;
final class AuthorizationServerFactory
{
    public const ISSUER = 'https://auth.tualo.io';

    public function __construct(
        private readonly Database $database,
        private readonly IdentityRepository $identityRepository,
        private readonly string $webRoot,
        private readonly string $privateKeyPath,
        private readonly ?string $privateKeyPassphrase,
        private readonly string $encryptionKey
    ) {
    }

    public function create(): AuthorizationServer
    {
        $privateKeyPath = realpath($this->privateKeyPath);
        if ($privateKeyPath === false || !is_file($privateKeyPath) || !is_readable($privateKeyPath)) {
            throw new \RuntimeException('The configured OAuth private key is not a readable file.');
        }

        $webRoot = realpath($this->webRoot);
        if ($webRoot === false || !is_dir($webRoot)) {
            throw new \InvalidArgumentException('The configured web root must be an existing directory.');
        }
        if ($privateKeyPath === $webRoot || str_starts_with($privateKeyPath, $webRoot . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('The OAuth private key must be stored outside the web root.');
        }

        try {
            $encryptionKey = Key::loadFromAsciiSafeString($this->encryptionKey);
        } catch (\Throwable $exception) {
            throw new \InvalidArgumentException(
                'The OAuth encryption key must be a valid Defuse ASCII-safe key.',
                previous: $exception
            );
        }

        $clientRepository = new ClientRepository($this->database);
        $accessTokenRepository = new AccessTokenRepository($this->database);
        $authCodeRepository = new AuthCodeRepository($this->database);
        $refreshTokenRepository = new RefreshTokenRepository($this->database);
        $scopeRepository = new ScopeRepository();
        $responseType = new FixedIssuerIdTokenResponse(
            $this->identityRepository,
            new ClaimExtractor(),
            self::ISSUER
        );

        $server = new AuthorizationServer(
            $clientRepository,
            $accessTokenRepository,
            $scopeRepository,
            new CryptKey('file://' . $privateKeyPath, $this->privateKeyPassphrase),
            $encryptionKey,
            $responseType
        );
        $server->revokeRefreshTokens(true);

        $authCodeGrant = new OfflineAccessAuthCodeGrant(
            $authCodeRepository,
            $refreshTokenRepository,
            new \DateInterval('PT5M')
        );
        $authCodeGrant->setRefreshTokenTTL(new \DateInterval('P30D'));
        $authCodeGrant->revokeRefreshTokens(true);
        $server->enableGrantType($authCodeGrant, new \DateInterval('PT15M'));

        $refreshTokenGrant = new OfflineAccessRefreshTokenGrant($refreshTokenRepository);
        $refreshTokenGrant->setRefreshTokenTTL(new \DateInterval('P30D'));
        $refreshTokenGrant->revokeRefreshTokens(true);
        $server->enableGrantType($refreshTokenGrant, new \DateInterval('PT15M'));

        $server->enableGrantType(new ClientCredentialsGrant(), new \DateInterval('PT15M'));

        return $server;
    }
}
