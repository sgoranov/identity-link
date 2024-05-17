<?php
declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\DataFixtures\AppFixtures;
use App\Service\OAuth2\AuthCodeService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AuthCodeRepositoryTest extends KernelTestCase
{
    private static AuthCodeService $authCodeRepository;
    private static \App\Repository\AuthCodeRepository $authCodeDoctrineRepository;

    public static function setUpBeforeClass(): void
    {
        $container = static::getContainer();
        self::$authCodeDoctrineRepository = $container->get(\App\Repository\AuthCodeRepository::class);
        self::$authCodeRepository = $container->get(AuthCodeService::class);
    }

    public function testRevokeAuthCode(): void
    {
        $code = self::$authCodeDoctrineRepository->getByIdentifier(AppFixtures::AUTH_CODE_PRIVATE_CLIENT_IDENTIFIER);
        $this->assertFalse(self::$authCodeRepository->isAuthCodeRevoked($code->getIdentifier()));

        self::$authCodeRepository->revokeAuthCode($code->getIdentifier());
        $this->assertTrue(self::$authCodeRepository->isAuthCodeRevoked($code->getIdentifier()));
    }
}