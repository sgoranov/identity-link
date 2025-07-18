<?php
declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\DataFixtures\AppFixtures;
use App\LeagueOAuth2\Entity\ClientEntity;
use App\LeagueOAuth2\Entity\GrantTypeEntity;
use App\LeagueOAuth2\Entity\ScopeEntity;
use App\LeagueOAuth2\Repository\ClientRepository;
use App\LeagueOAuth2\Repository\ScopeRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ScopeRepositoryTest extends KernelTestCase
{
    private static ClientRepository $clientRepository;
    private static ScopeRepository $scopeRepository;

    public static function setUpBeforeClass(): void
    {
        $container = static::getContainer();
        self::$clientRepository = $container->get(ClientRepository::class);
        self::$scopeRepository = $container->get(ScopeRepository::class);
    }

    public function testFinalizeScopes(): void
    {
        /** @var ClientEntity $client */
        $client = self::$clientRepository->getClientEntity(AppFixtures::PRIVATE_CLIENT_IDENTIFIER);
        $client->setScopes([
            new ScopeEntity('profile'),
            new ScopeEntity('openid'),
        ]);

        $scopes = self::$scopeRepository->finalizeScopes([new ScopeEntity('profile')],
            GrantTypeEntity::CLIENT_CREDENTIALS, $client);
        $this->assertCount(1, $scopes);

        list($scope) = $scopes;
        $this->assertEquals('profile', (string) $scope);
    }

    public function testGetScopeEntityByIdentifier(): void
    {
        $scope = self::$scopeRepository->getScopeEntityByIdentifier('test');
        $this->assertFalse($scope);

        $scope = self::$scopeRepository->getScopeEntityByIdentifier('openid');
        $this->assertEquals('openid', $scope->getIdentifier());
    }
}