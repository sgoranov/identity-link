<?php
declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\DataFixtures\AppFixtures;
use App\Model\OAuth2\ClientModel;
use App\Model\OAuth2\GrantTypeModel;
use App\Model\OAuth2\ScopeModel;
use App\Service\OAuth2\ClientService;
use App\Service\OAuth2\ScopeService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ScopeRepositoryTest extends KernelTestCase
{
    private static ClientService $clientRepository;
    private static ScopeService $scopeRepository;

    public static function setUpBeforeClass(): void
    {
        $container = static::getContainer();
        self::$clientRepository = $container->get(ClientService::class);
        self::$scopeRepository = $container->get(ScopeService::class);
    }

    public function testFinalizeScopes(): void
    {
        /** @var ClientModel $client */
        $client = self::$clientRepository->getClientEntity(AppFixtures::PRIVATE_CLIENT_IDENTIFIER);
        $client->setScopes([
            new ScopeModel(ScopeModel::PROFILE),
            new ScopeModel(ScopeModel::OPENID),
        ]);

        $scopes = self::$scopeRepository->finalizeScopes([new ScopeModel(ScopeModel::PROFILE)],
            GrantTypeModel::CLIENT_CREDENTIALS, $client);
        $this->assertCount(1, $scopes);

        list($scope) = $scopes;
        $this->assertEquals(ScopeModel::PROFILE, (string) $scope);
    }
}