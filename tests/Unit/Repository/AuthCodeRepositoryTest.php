<?php
declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\DataFixtures\AppFixtures;
use App\LeagueOAuth2\Repository\AuthCodeRepository;
use App\Repository\AccessTokenRepository;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AuthCodeRepositoryTest extends KernelTestCase
{
    private static AuthCodeRepository $authCodeRepository;
    private static \App\Repository\AuthCodeRepository $authCodeDoctrineRepository;

    public static function setUpBeforeClass(): void
    {
        $container = static::getContainer();
        self::$authCodeDoctrineRepository = $container->get(\App\Repository\AuthCodeRepository::class);
        self::$authCodeRepository = $container->get(AuthCodeRepository::class);
    }

    public function testRevokeAuthCode(): void
    {
        $code = self::$authCodeDoctrineRepository->getByIdentifier(AppFixtures::AUTH_CODE_PRIVATE_CLIENT_IDENTIFIER);
        $this->assertFalse(self::$authCodeRepository->isAuthCodeRevoked($code->getIdentifier()));

        self::$authCodeRepository->revokeAuthCode($code->getIdentifier());
        $this->assertTrue(self::$authCodeRepository->isAuthCodeRevoked($code->getIdentifier()));
    }

    public function testReusedAuthCodeRevokesTokensLinkedToIt(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $authCodeRepository = $container->get(AuthCodeRepository::class);
        $this->linkAccessTokenToAuthCode();

        $authCodeRepository->revokeAuthCode(AppFixtures::AUTH_CODE_PRIVATE_CLIENT_IDENTIFIER);
        $this->assertTrue($authCodeRepository->isAuthCodeRevoked(AppFixtures::AUTH_CODE_PRIVATE_CLIENT_IDENTIFIER));

        $entityManager->clear();
        $this->assertTrue($this->isAccessTokenRevoked(AppFixtures::ACCESS_TOKEN_IDENTIFIER));
        $this->assertTrue($this->isRefreshTokenRevoked(AppFixtures::REFRESH_TOKEN_IDENTIFIER));

        // Tokens that are not linked to the code are left alone.
        $this->assertFalse($this->isAccessTokenRevoked(AppFixtures::EXPIRED_ACCESS_TOKEN_IDENTIFIER));
        $this->assertFalse($this->isRefreshTokenRevoked(AppFixtures::ANOTHER_REFRESH_TOKEN_IDENTIFIER));
    }

    public function testUnusedAuthCodeLeavesTokensLinkedToItAlone(): void
    {
        $container = static::getContainer();
        $this->linkAccessTokenToAuthCode();

        $this->assertFalse(
            $container->get(AuthCodeRepository::class)->isAuthCodeRevoked(AppFixtures::AUTH_CODE_PRIVATE_CLIENT_IDENTIFIER),
        );

        $container->get(EntityManagerInterface::class)->clear();
        $this->assertFalse($this->isAccessTokenRevoked(AppFixtures::ACCESS_TOKEN_IDENTIFIER));
        $this->assertFalse($this->isRefreshTokenRevoked(AppFixtures::REFRESH_TOKEN_IDENTIFIER));
    }

    private function linkAccessTokenToAuthCode(): void
    {
        $container = static::getContainer();
        $container->get(AccessTokenRepository::class)
            ->getByIdentifier(AppFixtures::ACCESS_TOKEN_IDENTIFIER)
            ->setAuthCodeIdentifier(AppFixtures::AUTH_CODE_PRIVATE_CLIENT_IDENTIFIER);
        $container->get(EntityManagerInterface::class)->flush();
    }

    private function isAccessTokenRevoked(string $identifier): bool
    {
        return static::getContainer()->get(AccessTokenRepository::class)->getByIdentifier($identifier)->isRevoked();
    }

    private function isRefreshTokenRevoked(string $identifier): bool
    {
        return static::getContainer()->get(RefreshTokenRepository::class)->getByIdentifier($identifier)->isRevoked();
    }
}