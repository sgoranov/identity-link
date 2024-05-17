<?php
declare(strict_types=1);

namespace  App\Tests\Unit\Repository;

use App\DataFixtures\AppFixtures;
use App\Model\OAuth2\GrantTypeModel;
use App\Service\OAuth2\ClientService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ClientRepositoryTest extends KernelTestCase
{
    private static ClientService $clientRepository;

    public static function setUpBeforeClass(): void
    {
        $container = static::getContainer();
        self::$clientRepository = $container->get(ClientService::class);
    }

    public function testGetClientByIdentifier(): void
    {
        $client = self::$clientRepository->getClientEntity(AppFixtures::PRIVATE_CLIENT_IDENTIFIER);
        $this->assertEquals(AppFixtures::PRIVATE_CLIENT_NAME, $client->getName());

        $client = self::$clientRepository->getClientEntity('no_such_client');
        $this->assertNull($client);
    }

    public function testPublicClientValidity(): void
    {
        $result = self::$clientRepository->validateClient(
            AppFixtures::PUBLIC_CLIENT_IDENTIFIER,
            null,
            GrantTypeModel::AUTHORIZATION_CODE
        );
        $this->assertFalse($result);

        $result = self::$clientRepository->validateClient(
            AppFixtures::PUBLIC_CLIENT_IDENTIFIER,
            null,
            GrantTypeModel::CLIENT_CREDENTIALS
        );
        $this->assertTrue($result);
    }

    public function testPrivateClientValidity(): void
    {
        $result = self::$clientRepository->validateClient(
            AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
            'wrong_password',
            GrantTypeModel::AUTHORIZATION_CODE
        );
        $this->assertFalse($result);

        $result = self::$clientRepository->validateClient(
            AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
            AppFixtures::PRIVATE_CLIENT_SECRET,
            GrantTypeModel::AUTHORIZATION_CODE
        );
        $this->assertTrue($result);

        $result = self::$clientRepository->validateClient(
            AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
            AppFixtures::PRIVATE_CLIENT_EXPIRED_SECRET,
            GrantTypeModel::AUTHORIZATION_CODE
        );
        $this->assertFalse($result);
    }
}