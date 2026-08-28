<?php
declare(strict_types=1);

namespace App\Tests\Unit\LeagueOAuth2\Repository;

use App\Api\Contract\ClientConnectorInterface;
use App\Api\Contract\UserConnectorInterface;
use App\Entity\AuthCode;
use App\LeagueOAuth2\Entity\ClientEntity;
use App\LeagueOAuth2\Entity\ScopeEntity;
use App\LeagueOAuth2\Repository\ScopeRepository;
use App\Repository\AuthCodeRepository;
use App\Security\Authorization\AuthorizationRegistry;
use App\Security\Authorization\Loader\AuthorizationLoaderInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use PHPUnit\Framework\TestCase;

final class ScopeRepositoryTest extends TestCase
{
    private const AUDIENCE = 'https://api.example';
    private const CLIENT_ID = 'client-id';
    private const USER_ID = 'user-id';
    private const AUTH_CODE_ID = 'auth-code-id';

    public function testRecognizesConfiguredScopesAndAliasesOnly(): void
    {
        $repository = $this->createRepository();

        self::assertSame('scope.a', (string) $repository->getScopeEntityByIdentifier('scope.a'));
        self::assertSame('requested.all', (string) $repository->getScopeEntityByIdentifier('requested.all'));
        self::assertNull($repository->getScopeEntityByIdentifier('unknown'));
    }

    public function testClientCredentialsScopesAreLimitedByClientOnly(): void
    {
        $repository = $this->createRepository(
            clientScopes: ['client.allowed'],
            userScopes: [],
        );

        self::assertSame(
            ['scope.c', 'scope.d', 'scope.e'],
            $this->finalize($repository, ['requested.all'], 'client_credentials'),
        );
    }

    public function testUserFlowScopesAreLimitedByClientAndUser(): void
    {
        $repository = $this->createRepository(
            clientScopes: ['auth-code.allowed'],
            userScopes: ['user.allowed'],
        );

        self::assertSame(
            ['scope.d', 'scope.e'],
            $this->finalize(
                $repository,
                ['requested.all'],
                'password',
                userIdentifier: self::USER_ID,
            ),
        );
    }

    public function testAuthorizationCodeFlowAppliesAllThreeFiltersInOrder(): void
    {
        $repository = $this->createRepository(
            clientScopes: ['client.allowed'],
            userScopes: ['user.allowed'],
            authCodeScopes: ['auth-code.allowed'],
        );

        self::assertSame(
            ['scope.d', 'scope.e'],
            $this->finalize(
                $repository,
                ['requested.all'],
                'authorization_code',
                self::USER_ID,
                self::AUTH_CODE_ID,
            ),
        );
    }

    public function testReturnsNoScopesWhenClientHasNoAssignedScopes(): void
    {
        $repository = $this->createRepository(
            clientScopes: [],
            userScopes: ['requested.all'],
        );

        self::assertSame(
            [],
            $this->finalize(
                $repository,
                ['requested.all'],
                'password',
                userIdentifier: self::USER_ID,
            ),
        );
    }

    public function testReturnsNoScopesWhenNoneAreRequested(): void
    {
        $repository = $this->createRepository(
            clientScopes: ['requested.all'],
            userScopes: ['requested.all'],
        );

        self::assertSame(
            [],
            $this->finalize(
                $repository,
                [],
                'password',
                userIdentifier: self::USER_ID,
            ),
        );
    }

    public function testRejectsClientWithoutApplicationAudienceContract(): void
    {
        $repository = $this->createRepository();
        $client = $this->createMock(ClientEntityInterface::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Client entity must implement');

        $repository->finalizeScopes([], 'client_credentials', $client);
    }

    /**
     * @param list<string> $clientScopes
     * @param list<string> $userScopes
     * @param list<string>|null $authCodeScopes
     */
    private function createRepository(
        array $clientScopes = [],
        array $userScopes = [],
        ?array $authCodeScopes = null,
    ): ScopeRepository {
        $loader = $this->createStub(AuthorizationLoaderInterface::class);
        $loader->method('load')->willReturn($this->createRegistry());

        $clientConnector = $this->createStub(ClientConnectorInterface::class);
        $clientConnector
            ->method('getScopes')
            ->willReturnCallback(static function (string $clientId, string $audience) use ($clientScopes): array {
                self::assertSame(self::CLIENT_ID, $clientId);
                self::assertSame(self::AUDIENCE, $audience);

                return $clientScopes;
            });

        $userConnector = $this->createStub(UserConnectorInterface::class);
        $userConnector
            ->method('getScopes')
            ->willReturnCallback(static function (string $userId, string $audience) use ($userScopes): array {
                self::assertSame(self::USER_ID, $userId);
                self::assertSame(self::AUDIENCE, $audience);

                return $userScopes;
            });

        $authCodeRepository = $this->createStub(AuthCodeRepository::class);
        if (null !== $authCodeScopes) {
            $authCode = new AuthCode();
            $authCode->setScopes(json_encode($authCodeScopes, JSON_THROW_ON_ERROR));
            $authCodeRepository
                ->method('getByIdentifier')
                ->willReturnCallback(static function (string $authCodeId) use ($authCode): AuthCode {
                    self::assertSame(self::AUTH_CODE_ID, $authCodeId);

                    return $authCode;
                });
        }

        return new ScopeRepository(
            $clientConnector,
            $userConnector,
            $authCodeRepository,
            $loader,
        );
    }

    /**
     * @param list<string> $scopes
     * @return list<string>
     */
    private function finalize(
        ScopeRepository $repository,
        array $scopes,
        string $grantType,
        ?string $userIdentifier = null,
        ?string $authCodeId = null,
    ): array {
        $client = new ClientEntity();
        $client->setIdentifier(self::CLIENT_ID);
        $client->setAudience(self::AUDIENCE);

        return array_map(
            'strval',
            $repository->finalizeScopes(
                array_map(static fn (string $scope): ScopeEntity => new ScopeEntity($scope), $scopes),
                $grantType,
                $client,
                $userIdentifier,
                $authCodeId,
            ),
        );
    }

    private function createRegistry(): AuthorizationRegistry
    {
        return new AuthorizationRegistry([
            self::AUDIENCE => [
                'scopes' => [
                    'scope.a' => ['description' => 'Scope A'],
                    'scope.b' => ['description' => 'Scope B'],
                    'scope.c' => ['description' => 'Scope C'],
                    'scope.d' => ['description' => 'Scope D'],
                    'scope.e' => ['description' => 'Scope E'],
                ],
                'aliases' => [
                    'requested.all' => [
                        'description' => 'All requested scopes',
                        'scopes' => ['scope.a', 'scope.b', 'scope.c', 'scope.d', 'scope.e'],
                    ],
                    'auth-code.allowed' => [
                        'description' => 'Scopes granted by the authorization code',
                        'scopes' => ['scope.b', 'scope.c', 'scope.d', 'scope.e'],
                    ],
                    'client.allowed' => [
                        'description' => 'Scopes assigned to the client',
                        'scopes' => ['scope.c', 'scope.d', 'scope.e'],
                    ],
                    'user.allowed' => [
                        'description' => 'Scopes assigned to the user',
                        'scopes' => ['scope.d', 'scope.e'],
                    ],
                ],
            ],
        ]);
    }
}
