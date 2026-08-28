<?php
declare(strict_types=1);

namespace App\Service;

use App\Security\Authorization\AuthorizationRegistry;
use App\Security\Authorization\Loader\AuthorizationLoaderInterface;
use App\Security\Jwt\JwtConfig;
use Firebase\JWT\JWT;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class JwtTokenGenerator
{
    private string $subject; // Identifies the subject of the JWT, typically the user or entity it represents.
    private int $expTime = 3600;
    private array $scopes = ['identity-link.core'];
    private readonly AuthorizationRegistry $authorizationRegistry;

    public function __construct(
        private readonly JwtConfig $jwtConfig,
        private readonly CacheInterface $cache,
        AuthorizationLoaderInterface $authorizationLoader,
    )
    {
        $this->authorizationRegistry = $authorizationLoader->load();
    }

    public static function isJWT(string $token): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        try {
            $header = JWT::jsonDecode(JWT::urlsafeB64Decode($parts[0]));
            return isset($header->alg) && is_string($header->alg);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function createTokenByPayload(array $payload): string
    {
        $key = file_get_contents($this->jwtConfig->getPrivateKey());

        return JWT::encode($payload, $key, 'RS256', null, ['kid' => $this->jwtConfig->getKid()]);
    }

    public function createToken(): string
    {
        return $this->createTokenByPayload([
            'iss' => $this->getIssuer(),
            'aud' => $this->getAudience(),
            'sub' => $this->getSubject(),
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + $this->getExpTime(),
            'scope' => implode(' ', $this->getScopes()),
        ]);
    }

    public function loadTokenFromCache(): string
    {
        if ($this->getExpTime() - 1 <= 0) {
            throw new \InvalidArgumentException('Expiration time is not valid');
        }

        return $this->cache->get($this->computeIdentifierHash(), function (ItemInterface $item) {
            $item->expiresAfter($this->getExpTime() - 1);
            return $this->createToken();
        });
    }

    public function getIssuer(): string
    {
        return $this->jwtConfig->getIssuer();
    }

    public function getAudience(): string
    {
        return $this->jwtConfig->getAudience();
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function getExpTime(): int
    {
        return $this->expTime;
    }

    public function setExpTime(int $expTime): self
    {
        $this->expTime = $expTime;

        return $this;
    }

    public function getScopes(): array
    {
        return $this->authorizationRegistry->expandScopes($this->getAudience(), $this->scopes);
    }

    public function setScopes(array $scopes): self
    {
        $this->scopes = $scopes;

        return $this;
    }

    private function computeIdentifierHash(): string
    {
        return 'jwt_token_' . sha1(vsprintf('%s-%s-%s-%s-%s', [
            $this->getAudience(),
            $this->getSubject(),
            $this->getIssuer(),
            $this->getExpTime(),
            implode(' ', $this->getScopes())
        ]));
    }
}
