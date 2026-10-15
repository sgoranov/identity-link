<?php
declare(strict_types=1);

namespace App\Tests\Unit\Security\Authorization;

use App\Security\Authorization\AuthorizationRegistry;
use App\Security\Authorization\UnknownAudienceException;
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
        $this->expectException(UnknownAudienceException::class);
        $this->expectExceptionMessage('Unknown audience "unknown".');

        (new AuthorizationRegistry())->getScopesAndAliases('unknown');
    }

    /**
     * @dataProvider emptyAudienceProvider
     */
    public function testRejectsEmptyAudience(string $audience): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Audience "%s" must not be empty.', $audience));

        new AuthorizationRegistry([
            'https://api.example' => [],
            $audience => [],
        ]);
    }

    /** @return array<string, array{string}> */
    public static function emptyAudienceProvider(): array
    {
        return [
            'empty' => [''],
            'whitespace' => ['   '],
        ];
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

    public function testOpenIdConnectResourcesScopesAreSharedByEveryAudience(): void
    {
        $registry = new AuthorizationRegistry(
            [
                'https://api.example' => [
                    'scopes' => [
                        'users.read' => ['description' => 'View users'],
                    ],
                ],
                'https://login-only.example' => [],
            ],
            $this->openIdConnectResources(),
        );

        self::assertSame(['https://api.example', 'https://login-only.example'], $registry->getAudiences());
        self::assertTrue($registry->containsScopeOrAlias('openid'));
        self::assertTrue($registry->containsScopeOrAlias('oidc.default'));
        self::assertSame(
            ['openid', 'email', 'users.read'],
            $registry->expandScopes('https://api.example', ['oidc.default', 'users.read']),
        );
        self::assertSame(
            ['openid', 'email'],
            $registry->expandScopes('https://login-only.example', ['oidc.default', 'users.read']),
        );
        // The metadata of an audience lists only its own scopes and aliases; the shared ones are listed separately.
        self::assertSame(
            ['scopes' => [], 'aliases' => []],
            $registry->getScopesAndAliases('https://login-only.example'),
        );
        self::assertSame($this->openIdConnectResources(), $registry->getOpenIdConnectResources());
    }

    public function testReturnsOpenIdConnectResourcesScopesWithoutAliasesOrResourceScopes(): void
    {
        $registry = new AuthorizationRegistry(
            [
                'https://api.example' => [
                    'scopes' => [
                        'users.read' => ['description' => 'View users'],
                    ],
                ],
            ],
            $this->openIdConnectResources(),
        );

        self::assertSame(['openid', 'email'], $registry->getOpenIdConnectResourcesScopes());
    }

    public function testResourceAliasMayIncludeOpenIdConnectResourcesScopes(): void
    {
        $registry = new AuthorizationRegistry(
            [
                'https://api.example' => [
                    'scopes' => [
                        'users.read' => ['description' => 'View users'],
                    ],
                    'aliases' => [
                        'app.default' => [
                            'description' => 'Sign in and view users',
                            'scopes' => ['openid', 'users.read'],
                        ],
                    ],
                ],
            ],
            $this->openIdConnectResources(),
        );

        self::assertSame(['openid', 'users.read'], $registry->expandScopes('https://api.example', ['app.default']));
    }

    public function testRejectsOpenIdConnectResourcesAliasReferencingResourceScope(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Scope alias "oidc.users" for "openid_connect_resources" references undefined scope "users.read".',
        );

        new AuthorizationRegistry(
            [
                'https://api.example' => [
                    'scopes' => [
                        'users.read' => ['description' => 'View users'],
                    ],
                ],
            ],
            [
                'scopes' => [
                    'openid' => ['description' => 'Sign in'],
                ],
                'aliases' => [
                    'oidc.users' => [
                        'description' => 'Sign in and view users',
                        'scopes' => ['openid', 'users.read'],
                    ],
                ],
            ],
        );
    }

    /** @return array{scopes: array<string, mixed>, aliases: array<string, mixed>} */
    private function openIdConnectResources(): array
    {
        return [
            'scopes' => [
                'openid' => ['description' => 'Sign in'],
                'email' => ['description' => 'Read the email address'],
            ],
            'aliases' => [
                'oidc.default' => [
                    'description' => 'Standard identity scopes',
                    'scopes' => ['openid', 'email'],
                ],
            ],
        ];
    }
}
