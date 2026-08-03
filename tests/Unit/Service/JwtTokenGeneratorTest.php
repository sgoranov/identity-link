<?php
declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Security\Jwt\JwtConfig;
use App\Service\JwtTokenGenerator;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class JwtTokenGeneratorTest extends TestCase
{
    public function testUsesConfiguredIssuerAndAudience(): void
    {
        $jwtConfig = new JwtConfig(new ArrayAdapter(), [
            'issuer' => 'https://example.com/identity-link',
            'audience' => 'https://example.com/identity-link',
            'key' => [
                'public' => 'file://' . dirname(__DIR__, 2) . '/resources/public.key',
                'private' => 'file://' . dirname(__DIR__, 2) . '/resources/private.key',
            ],
        ]);
        $generator = new JwtTokenGenerator($jwtConfig, new ArrayAdapter());

        $token = $generator
            ->setSubject('internal')
            ->createToken();
        $payload = JWT::jsonDecode(JWT::urlsafeB64Decode(explode('.', $token)[1]));

        $this->assertSame('https://example.com/identity-link', $payload->iss);
        $this->assertSame('https://example.com/identity-link', $payload->aud);
    }
}
