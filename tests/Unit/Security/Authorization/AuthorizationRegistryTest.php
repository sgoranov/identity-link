<?php
declare(strict_types=1);

namespace App\Tests\Unit\Security\Authorization;

use App\Security\Authorization\AuthorizationRegistry;
use PHPUnit\Framework\TestCase;

final class AuthorizationRegistryTest extends TestCase
{
    public function testReturnsConfiguredAudiencesAndTheirMetadata(): void
    {
        $registry = new AuthorizationRegistry([
            'https://api.example' => [
                'scopes' => [
                    'users.read' => ['description' => 'View users'],
                ],
                'aliases' => [
                    'users.all' => [
                        'description' => 'All user scopes',
                        'scopes' => ['users.read'],
                    ],
                ],
            ],
        ]);

        self::assertSame(['https://api.example'], $registry->getAudiences());
        self::assertSame([
            'scopes' => [
                'users.read' => ['description' => 'View users'],
            ],
            'aliases' => [
                'users.all' => [
                    'description' => 'All user scopes',
                    'scopes' => ['users.read'],
                ],
            ],
        ], $registry->getScopesAndAliases('https://api.example'));
    }

    public function testRejectsMetadataRequestForUnknownAudience(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown audience "unknown".');

        (new AuthorizationRegistry())->getScopesAndAliases('unknown');
    }

    public function testExpandsAliasToUniqueConcreteScopes(): void
    {
        $registry = new AuthorizationRegistry([
            'https://api.example' => [
                'scopes' => [
                    'users.read' => ['description' => 'View users'],
                    'users.write' => ['description' => 'Update users'],
                ],
                'aliases' => [
                    'users.manage' => [
                        'description' => 'Manage users',
                        'scopes' => ['users.read', 'users.write'],
                    ],
                ],
            ],
        ]);

        self::assertTrue($registry->containsScopeOrAlias('users.manage'));
        self::assertSame(
            ['users.read', 'users.write'],
            $registry->expandScopes('https://api.example', ['users.manage', 'users.read']),
        );
    }

    public function testRejectsAliasReferencingUndefinedScope(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('references undefined scope "users.delete"');

        new AuthorizationRegistry([
            'https://api.example' => [
                'scopes' => [],
                'aliases' => [
                    'users.manage' => [
                        'description' => 'Manage users',
                        'scopes' => ['users.delete'],
                    ],
                ],
            ],
        ]);
    }

    public function testSkipsUndefinedScopeIdentifiers(): void
    {
        $registry = new AuthorizationRegistry([
            'https://api.example' => [
                'scopes' => [
                    'users.read' => ['description' => 'View users'],
                    'users.write' => ['description' => 'Update users'],
                ],
            ],
        ]);

        self::assertSame(
            ['users.read', 'users.write'],
            $registry->expandScopes(
                'https://api.example',
                ['invalid', 'users.read', 'users.write', 'also.invalid'],
            ),
        );
        self::assertSame(
            [],
            $registry->expandScopes('https://api.example', ['invalid']),
        );
    }

    public function testSkipsScopeDefinedOnlyForAnotherAudience(): void
    {
        $registry = new AuthorizationRegistry([
            'https://api-a.example' => [
                'scopes' => [
                    'api-a.read' => ['description' => 'Read API A'],
                ],
                'aliases' => [
                    'api-a.all' => [
                        'description' => 'All API A scopes',
                        'scopes' => ['api-a.read'],
                    ],
                ],
            ],
            'https://api-b.example' => [
                'scopes' => [
                    'api-b.read' => ['description' => 'Read API B'],
                ],
                'aliases' => [
                    'api-b.all' => [
                        'description' => 'All API B scopes',
                        'scopes' => ['api-b.read'],
                    ],
                ],
            ],
        ]);

        self::assertSame(
            ['api-a.read'],
            $registry->expandScopes(
                'https://api-a.example',
                ['api-a.all', 'api-b.read', 'api-b.all'],
            ),
        );
        self::assertSame(
            [],
            $registry->expandScopes(
                'https://api-a.example',
                ['api-b.read', 'api-b.all'],
            ),
        );
    }

}
