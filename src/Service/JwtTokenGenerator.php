<?php
declare(strict_types=1);

namespace App\Service;

use Firebase\JWT\JWT;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class JwtTokenGenerator
{
    private string $issuer; // Identifies the entity that issued the JWT
    private string $audience; // Identifies the recipients for whom the JWT is intended.
    private string $subject; // Identifies the subject of the JWT, typically the user or entity it represents.
    private int $expTime = 3600;
    private array $groups = [];

    public function __construct(
        private readonly array $jwtKey,
    )
    {
    }

    public function createToken(): string
    {
        $key = file_get_contents($this->jwtKey['private']);

        $payload = [
            'iss' => $this->getIssuer(),
            'aud' => $this->getAudience(),
            'sub' => $this->getSubject(),
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + $this->getExpTime(),
            'groups' => implode(' ', $this->getGroups()),
        ];

        return JWT::encode($payload, $key, 'RS256', null, ['kid' => $this->jwtKey['kid']]);
    }

    public function loadTokenFromCache(): string
    {
        if ($this->getExpTime() - 1 <= 0) {
            throw new \InvalidArgumentException('Expiration time is not valid');
        }

        $cache = new FilesystemAdapter(
            $namespace = 'JwtTokenGenerator',

            // the default lifetime (in seconds) for cache items that do not define their
            // own lifetime, with a value 0 causing items to be stored indefinitely (i.e.
            // until the files are deleted)
            $defaultLifetime = $this->getExpTime() - 1,

            // the main cache directory (the application needs read-write permissions on it)
            // if none is specified, a directory is created inside the system temporary directory
            $directory = null
        );

        return $cache->get($this->computeIdentifierHash(), [$this, 'createToken']);
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
        return sha1(vsprintf('%s-%s-%s-%s-%s', [
            $this->getAudience(),
            $this->getSubject(),
            $this->getIssuer(),
            $this->getExpTime(),
            implode(' ', $this->getGroups())
        ]));
    }
}