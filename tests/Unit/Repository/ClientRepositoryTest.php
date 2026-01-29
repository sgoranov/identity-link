<?php
declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\DataFixtures\AppFixtures;
use App\LeagueOAuth2\Repository\ClientRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ClientRepositoryTest extends KernelTestCase
{
    private static ClientRepository $clientRepository;

    public static function setUpBeforeClass(): void
    {
        $container = static::getContainer();
        self::$clientRepository = $container->get(ClientRepository::class);
    }

    public function testGetClientEntityWithMultipleRedirectUris(): void
    {
        $client = self::$clientRepository->getClientEntity(AppFixtures::MULTI_REDIRECT_CLIENT_IDENTIFIER);

        $this->assertIsArray($client->getRedirectUri());
        $this->assertSame(AppFixtures::MULTI_REDIRECT_CLIENT_REDIRECT_URIS, $client->getRedirectUri());
    }

    public function testGetClientEntityWithSingleRedirectUri(): void
    {
        $client = self::$clientRepository->getClientEntity(AppFixtures::PRIVATE_CLIENT_IDENTIFIER);

        $this->assertIsString($client->getRedirectUri());
        $this->assertSame(AppFixtures::PRIVATE_CLIENT_REDIRECT_URI, $client->getRedirectUri());
    }
}
