<?php
declare(strict_types=1);

namespace App\Security\Jwt;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class JwtConfig
{
    private array $config;

    public function __construct(
        private readonly CacheInterface $cache,
        array $config
    )
    {
        $this->config = $config;
    }

    public function getKid(): ?string
    {
        $cacheKey = 'jwt_key_thumbprint_' . md5($this->getPublicKey());
        return $this->cache->get($cacheKey, function (ItemInterface $item) {
            $item->expiresAfter(2592000); // 30 days

            $pubKeyDetails = openssl_pkey_get_details(openssl_pkey_get_public(file_get_contents($this->getPublicKey())));
            if (!$pubKeyDetails || !isset($pubKeyDetails['rsa'])) {
                throw new \RuntimeException("Invalid RSA public key");
            }

            $jwkEssential = [
                'e'   => rtrim(strtr(base64_encode($pubKeyDetails['rsa']['e']), '+/', '-_'), '='),
                'kty' => 'RSA',
                'n'   => rtrim(strtr(base64_encode($pubKeyDetails['rsa']['n']), '+/', '-_'), '=')
            ];

            return rtrim(strtr(base64_encode(hash('sha256', json_encode($jwkEssential), true)), '+/', '-_'), '=');
        });
    }

    public function getPublicKey(): ?string
    {
        return $this->config['key']['public'] ?? null;
    }

    public function getPrivateKey(): ?string
    {
        return $this->config['key']['private'] ?? null;
    }

    public function getIssuer(): string
    {
        return $this->config['issuer'];
    }

    public function getAudience(): string
    {
        return $this->config['audience'];
    }
}
