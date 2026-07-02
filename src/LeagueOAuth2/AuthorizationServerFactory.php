<?php
declare(strict_types=1);

namespace App\LeagueOAuth2;

use App\LeagueOAuth2\Repository\AccessTokenRepository;
use App\LeagueOAuth2\Repository\AuthCodeRepository;
use App\LeagueOAuth2\Repository\ClientRepository;
use App\LeagueOAuth2\Repository\RefreshTokenRepository;
use App\LeagueOAuth2\Repository\ScopeRepository;
use App\LeagueOAuth2\Repository\UserRepository;
use App\Security\EncryptionKeyLoader;
use App\Security\Jwt\JwtConfig;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\EventEmitting\EventEmitter;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\ImplicitGrant;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\RequestEvent;
use OpenIDConnectServer\IdTokenResponse;
use Psr\EventDispatcher\EventDispatcherInterface;

class AuthorizationServerFactory
{
    private string $accessToken_Ttl;

    private string $refreshTokenTtl;

    private string $authCodeTtl;

    private bool $enableClientCredentialsGrant;

    private bool $enablePasswordGrant;

    private bool $enableRefreshTokenGrant;

    private bool $enableAuthCodeGrant;

    private bool $enableImplicitGrant;

    public function __construct(
        private readonly AccessTokenRepository $accessTokenRepository,
        private readonly AuthCodeRepository $authCodeRepository,
        private readonly ClientRepository $clientRepository,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly ScopeRepository $scopeRepository,
        private readonly UserRepository $userRepository,
        private readonly IdTokenResponse $idTokenResponse,
        private readonly JwtConfig $jwtConfig,
        private readonly EncryptionKeyLoader $encryptionKeyLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
    )
    {
    }

    public function setAccessTokenTtl(string $accessToken_Ttl): void
    {
        $this->accessToken_Ttl = $accessToken_Ttl;
    }

    public function setRefreshTokenTtl(string $refreshTokenTtl): void
    {
        $this->refreshTokenTtl = $refreshTokenTtl;
    }

    public function setAuthCodeTtl(string $authCodeTtl): void
    {
        $this->authCodeTtl = $authCodeTtl;
    }

    public function enableClientCredentialsGrant(bool $enableClientCredentialsGrant): void
    {
        $this->enableClientCredentialsGrant = $enableClientCredentialsGrant;
    }

    public function enablePasswordGrant(bool $enablePasswordGrant): void
    {
        $this->enablePasswordGrant = $enablePasswordGrant;
    }

    public function enableRefreshTokenGrant(bool $enableRefreshTokenGrant): void
    {
        $this->enableRefreshTokenGrant = $enableRefreshTokenGrant;
    }

    public function enableAuthCodeGrant(bool $enableAuthCodeGrant): void
    {
        $this->enableAuthCodeGrant = $enableAuthCodeGrant;
    }

    public function enableImplicitGrant(bool $enableImplicitGrant): void
    {
        $this->enableImplicitGrant = $enableImplicitGrant;
    }

    public function create(): AuthorizationServer
    {
        $server = new AuthorizationServer(
            $this->clientRepository,
            $this->accessTokenRepository,
            $this->scopeRepository,
            new CryptKey($this->jwtConfig->getPrivateKey(), null, false, $this->jwtConfig->getKid()),
            $this->encryptionKeyLoader->loadEncryptionKey(),
            $this->idTokenResponse,
        );

        $emitter = new EventEmitter();
        $emitter->addListener(
            RequestEvent::CLIENT_AUTHENTICATION_FAILED,
            fn ($event) => $this->eventDispatcher->dispatch($event)
        );
        $emitter->addListener(
            RequestEvent::REFRESH_TOKEN_ISSUED,
            fn ($event) => $this->eventDispatcher->dispatch($event)
        );
        $emitter->addListener(
            RequestEvent::ACCESS_TOKEN_ISSUED,
            fn ($event) => $this->eventDispatcher->dispatch($event)
        );

        $server->setEmitter($emitter);

        if ($this->enableClientCredentialsGrant) {
            $grantType = new ClientCredentialsGrant();
            $server->enableGrantType($grantType, new \DateInterval($this->accessToken_Ttl));
        }

        if ($this->enablePasswordGrant) {
            $grantType = new PasswordGrant($this->userRepository, $this->refreshTokenRepository);
            $grantType->setRefreshTokenTTL(new \DateInterval($this->refreshTokenTtl));
            $server->enableGrantType($grantType, new \DateInterval($this->accessToken_Ttl));
        }

        if ($this->enableRefreshTokenGrant) {
            $grantType = new RefreshTokenGrant($this->refreshTokenRepository);
            $grantType->setRefreshTokenTTL(new \DateInterval($this->refreshTokenTtl));
            $server->enableGrantType($grantType, new \DateInterval($this->accessToken_Ttl));
        }

        if ($this->enableAuthCodeGrant) {
            $grantType = new AuthCodeGrant($this->authCodeRepository, $this->refreshTokenRepository,
                new \DateInterval($this->authCodeTtl));
            $grantType->setRefreshTokenTTL(new \DateInterval($this->refreshTokenTtl));
            $server->enableGrantType($grantType, new \DateInterval($this->accessToken_Ttl));
        }

        if ($this->enableImplicitGrant) {
            $grantType = new ImplicitGrant(new \DateInterval($this->accessToken_Ttl));
            $server->enableGrantType($grantType, new \DateInterval($this->accessToken_Ttl));
        }

        return $server;
    }
}