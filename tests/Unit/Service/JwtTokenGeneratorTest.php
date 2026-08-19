<?php
declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Security\Authorization\AuthorizationRegistry;
use App\Security\Authorization\Loader\AuthorizationLoaderInterface;
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
        $authorizationLoader = $this->createStub(AuthorizationLoaderInterface::class);
        $authorizationLoader->method('load')->willReturn(new AuthorizationRegistry([
            'https://example.com/identity-link' => [
                'scopes' => [
                    'users.read' => ['description' => 'View users'],
                    'users.query' => ['description' => 'Query users'],
                    'users.auth' => ['description' => 'Authenticate users'],
                    'users.groups.read' => ['description' => 'View user groups'],
                ],
                'aliases' => [
                    'identity-link.core' => [
                        'description' => 'Core permissions',
                        'scopes' => ['users.read', 'users.query', 'users.auth', 'users.groups.read'],
                    ],
                ],
            ],
        ]));
        $generator = new JwtTokenGenerator($jwtConfig, new ArrayAdapter(), $authorizationLoader);

        $token = $generator
            ->setSubject('internal')
            ->createToken();
        $payload = JWT::jsonDecode(JWT::urlsafeB64Decode(explode('.', $token)[1]));

        $this->assertSame('https://example.com/identity-link', $payload->iss);
        $this->assertSame('https://example.com/identity-link', $payload->aud);
        $this->assertSame(
            'users.read users.query users.auth users.groups.read',
            $payload->scope,
        );
        $this->assertObjectNotHasProperty('scopes', $payload);
        $this->assertObjectNotHasProperty('groups', $payload);
    }

    public function testReturnsUniqueConcreteScopesAndSkipsUnknownIdentifiers(): void
    {
        $jwtConfig = $this->createStub(JwtConfig::class);
        $jwtConfig->method('getAudience')->willReturn('https://example.com/identity-link');

        $authorizationLoader = $this->createStub(AuthorizationLoaderInterface::class);
        $authorizationLoader->method('load')->willReturn(new AuthorizationRegistry([
            'https://example.com/identity-link' => [
                'scopes' => [
                    'users.read' => ['description' => 'View users'],
                    'users.query' => ['description' => 'Query users'],
                ],
                'aliases' => [
                    'identity-link.core' => [
                        'description' => 'Core permissions',
                        'scopes' => ['users.read', 'users.query'],
                    ],
                ],
            ],
        ]));

        $generator = new JwtTokenGenerator($jwtConfig, new ArrayAdapter(), $authorizationLoader);
        $generator->setScopes(['identity-link.core', 'users.read', 'unknown', 'identity-link.core']);

        self::assertSame(['users.read', 'users.query'], $generator->getScopes());
    }
}
