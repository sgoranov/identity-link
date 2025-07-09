<?php
declare(strict_types=1);

namespace App\Security\Jwt;

class JwtConfig
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getKid(): ?string
    {
        return $this->config['kid'] ?? null;
    }

    public function getPublicKey(): ?string
    {
        return $this->config['public'] ?? null;
    }

    public function getPrivateKey(): ?string
    {
        return $this->config['private'] ?? null;
    }
}