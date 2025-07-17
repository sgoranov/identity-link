<?php
declare(strict_types=1);

namespace App\Service;

use App\Security\Jwt\JwtConfig;
use Firebase\JWT\JWT;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class JwtTokenGenerator
{
    private string $issuer; // Identifies the entity that issued the JWT
    private string $audience; // Identifies the recipients for whom the JWT is intended.
    private string $subject; // Identifies the subject of the JWT, typically the user or entity it represents.
    private int $expTime = 3600;
    private array $groups = [];

    public function __construct(
        private readonly JwtConfig $jwtConfig,
        private readonly CacheInterface $cache,
    )
    {
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
            'groups' => $this->getGroups(),
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
        return $this->issuer;
    }

    public function setIssuer(string $issuer): self
    {
        $this->issuer = $issuer;

        return $this;
    }

    public function getAudience(): string
    {
        return $this->audience;
    }

    public function setAudience(string $audience): self
    {
        $this->audience = $audience;

        return $this;
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

    public function getGroups(): array
    {
        return $this->groups;
    }

    public function setGroups(array $groups): self
    {
        $this->groups = $groups;

        return $this;
    }

    private function computeIdentifierHash(): string
    {
        return 'jwt_token_' . sha1(vsprintf('%s-%s-%s-%s-%s', [
            $this->getAudience(),
            $this->getSubject(),
            $this->getIssuer(),
            $this->getExpTime(),
            implode(' ', $this->getGroups())
        ]));
    }
}