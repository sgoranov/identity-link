<?php
declare(strict_types=1);

namespace App\Tests\Unit\Security\Jwt;

use App\Security\Jwt\JwtConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;

final class JwtConfigTest extends TestCase
{
    public function testExposesKeysIssuerAndAudience(): void
    {
        $config = new JwtConfig(
            $this->createMock(CacheInterface::class),
            [
                'issuer' => 'https://example.com/identity-link',
                'audience' => 'https://example.com/identity-link',
                'key' => [
                    'public' => 'file:///keys/public.pem',
                    'private' => 'file:///keys/private.pem',
                ],
            ],
        );

        $this->assertSame('https://example.com/identity-link', $config->getIssuer());
        $this->assertSame('https://example.com/identity-link', $config->getAudience());
        $this->assertSame('file:///keys/public.pem', $config->getPublicKey());
        $this->assertSame('file:///keys/private.pem', $config->getPrivateKey());
    }
}
