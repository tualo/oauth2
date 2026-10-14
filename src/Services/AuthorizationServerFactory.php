<?php

declare(strict_types=1);

namespace Tualo\Office\OAuth2\Services;

use Defuse\Crypto\Key;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use OpenIDConnectServer\ClaimExtractor;
use Tualo\Office\Basic\MYSQL\Database;
use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\OAuth2\Grants\OfflineAccessAuthCodeGrant;
use Tualo\Office\OAuth2\Grants\OfflineAccessRefreshTokenGrant;
use Tualo\Office\OAuth2\Repositories\AccessTokenRepository;
use Tualo\Office\OAuth2\Repositories\AuthCodeRepository;
use Tualo\Office\OAuth2\Repositories\ClientRepository;
use Tualo\Office\OAuth2\Repositories\IdentityRepository;
use Tualo\Office\OAuth2\Repositories\RefreshTokenRepository;
use Tualo\Office\OAuth2\Repositories\ScopeRepository;
use Tualo\Office\OAuth2\Setup;

final class AuthorizationServerFactory
{
    public function __construct(
        private readonly Database $database,
        private readonly IdentityRepository $identityRepository,
        private readonly string $webRoot,
        private readonly string $privateKeyPath,
        private readonly ?string $privateKeyPassphrase,
        private readonly string $encryptionKey
    ) {
    }

    public static function fromTualoApplication(Database $database): self
    {
        $privateKeyPath = App::configuration('oauth2', 'private_key_path', false);
        $encryptionKey = App::configuration('oauth2', 'encryption_key', false);
        $webRoot = $_SERVER['DOCUMENT_ROOT'] ?? false;
        $privateKeyPassphrase = App::configuration('oauth2', 'private_key_passphrase', null);

        if (!is_string($privateKeyPath) || trim($privateKeyPath) === '') {
            throw new \RuntimeException('OAuth configuration "private_key_path" is required.');
        }
        if (!is_string($encryptionKey) || trim($encryptionKey) === '') {
            throw new \RuntimeException('OAuth configuration "encryption_key" is required.');
        }
        if (!is_string($webRoot) || trim($webRoot) === '') {
            throw new \RuntimeException('The web server document root is unavailable.');
        }
        if ($privateKeyPassphrase !== null && !is_string($privateKeyPassphrase)) {
            throw new \RuntimeException('OAuth configuration "private_key_passphrase" must be a string.');
        }

        return new self(
            $database,
            new IdentityRepository($database),
            $webRoot,
            $privateKeyPath,
            $privateKeyPassphrase,
            $encryptionKey
        );
    }

    public function create(): AuthorizationServer
    {
        $setup = Setup::getInstance();
        $issuer = $setup->env('issuer_url');
        $authCodeTTL = $this->getConfiguredInterval($setup, 'auth_token_ttl');
        $accessTokenTTL = $this->getConfiguredInterval($setup, 'access_token_ttl');
        $refreshTokenTTL = $this->getConfiguredInterval($setup, 'refresh_token_ttl');

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
            $issuer
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
            $authCodeTTL
        );
        $authCodeGrant->setRefreshTokenTTL($refreshTokenTTL);
        $authCodeGrant->revokeRefreshTokens(true);
        $server->enableGrantType($authCodeGrant, $accessTokenTTL);

        $refreshTokenGrant = new OfflineAccessRefreshTokenGrant($refreshTokenRepository);
        $refreshTokenGrant->setRefreshTokenTTL($refreshTokenTTL);
        $refreshTokenGrant->revokeRefreshTokens(true);
        $server->enableGrantType($refreshTokenGrant, $accessTokenTTL);

        $server->enableGrantType(new ClientCredentialsGrant(), $accessTokenTTL);

        return $server;
    }

    private function getConfiguredInterval(Setup $setup, string $key): \DateInterval
    {
        $value = $setup->env($key);
        try {
            $interval = new \DateInterval($value);
        } catch (\Exception $exception) {
            throw new \InvalidArgumentException(
                sprintf('OAuth configuration "%s" must be a valid ISO-8601 duration.', $key),
                previous: $exception
            );
        }

        $epoch = new \DateTimeImmutable('@0');
        if ($epoch->add($interval) <= $epoch) {
            throw new \InvalidArgumentException(
                sprintf('OAuth configuration "%s" must be greater than zero.', $key)
            );
        }

        return $interval;
    }
}
