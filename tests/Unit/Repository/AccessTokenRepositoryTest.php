<?php
declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\DataFixtures\AppFixtures;
use App\Service\OAuth2\AccessTokenService;
use App\Service\OAuth2\ClientService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AccessTokenRepositoryTest extends KernelTestCase
{
    private static ClientService $clientRepository;
    private static AccessTokenService $accessTokenRepository;

    public static function setUpBeforeClass(): void
    {
        $container = static::getContainer();
        self::$accessTokenRepository = $container->get(AccessTokenService::class);
        self::$clientRepository = $container->get(ClientService::class);
    }

    public function testGetNewToken(): void
    {
        $client = self::$clientRepository->getClientEntity(AppFixtures::PRIVATE_CLIENT_IDENTIFIER);

        $token = self::$accessTokenRepository->getNewToken($client, [], AppFixtures::USER_IDENTIFIER);
        $this->assertEmpty($token->getScopes());

        $token = self::$accessTokenRepository->getNewToken($client, [], AppFixtures::USER_IDENTIFIER);
        $this->assertEquals(AppFixtures::USER_IDENTIFIER, $token->getUserIdentifier());

        $token = self::$accessTokenRepository->getNewToken($client, [], AppFixtures::USER_IDENTIFIER);
        $this->assertEquals(AppFixtures::PRIVATE_CLIENT_IDENTIFIER, $token->getClient()->getIdentifier());

        $token = self::$accessTokenRepository->getNewToken($client, [], null);
        $this->assertNull($token->getUserIdentifier());
    }

    public function testRevokeAccessToken(): void
    {
        $this->assertFalse(self::$accessTokenRepository->isAccessTokenRevoked(AppFixtures::ACCESS_TOKEN_IDENTIFIER));

        self::$accessTokenRepository->revokeAccessToken(AppFixtures::ACCESS_TOKEN_IDENTIFIER);
        $this->assertTrue(self::$accessTokenRepository->isAccessTokenRevoked(AppFixtures::ACCESS_TOKEN_IDENTIFIER));
    }
}