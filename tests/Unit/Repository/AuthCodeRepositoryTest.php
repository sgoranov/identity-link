<?php
declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\DataFixtures\AppFixtures;
use App\LeagueOAuth2\Repository\AuthCodeRepository;
use App\Repository\AccessTokenRepository;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
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

    public function testRevokingAuthCodeLeavesTokensLinkedToItAlone(): void
    {
        $container = static::getContainer();
        $authCodeRepository = $container->get(AuthCodeRepository::class);
        $this->linkAccessTokenToAuthCode();

        $authCodeRepository->revokeAuthCode(AppFixtures::AUTH_CODE_PRIVATE_CLIENT_IDENTIFIER);

        $container->get(EntityManagerInterface::class)->clear();
        $this->assertFalse($this->isAccessTokenRevoked(AppFixtures::ACCESS_TOKEN_IDENTIFIER));
        $this->assertFalse($this->isRefreshTokenRevoked(AppFixtures::REFRESH_TOKEN_IDENTIFIER));
    }

    public function testRevokingAuthCodeRevokedByConcurrentExchangeRevokesLinkedTokensAndFails(): void
    {
        $container = static::getContainer();
        $authCodeRepository = $container->get(AuthCodeRepository::class);
        $this->linkAccessTokenToAuthCode();

        // This exchange checked the code while it was unused...
        $this->assertFalse($authCodeRepository->isAuthCodeRevoked(AppFixtures::AUTH_CODE_PRIVATE_CLIENT_IDENTIFIER));
        // ...and a concurrent exchange revoked it before this one got to.
        $this->revokeAuthCodeInDatabase();

        try {
            $authCodeRepository->revokeAuthCode(AppFixtures::AUTH_CODE_PRIVATE_CLIENT_IDENTIFIER);
            $this->fail('Revoking a code revoked by a concurrent exchange must fail the exchange.');
        } catch (OAuthServerException $exception) {
            $this->assertSame('invalid_grant', $exception->getErrorType());
        }

        $container->get(EntityManagerInterface::class)->clear();
        $this->assertTrue($this->isAccessTokenRevoked(AppFixtures::ACCESS_TOKEN_IDENTIFIER));
        $this->assertTrue($this->isRefreshTokenRevoked(AppFixtures::REFRESH_TOKEN_IDENTIFIER));
    }

    public function testReusedAuthCodeIsLoggedAsError(): void
    {
        $logs = $this->captureLogs();
        $authCodeRepository = static::getContainer()->get(AuthCodeRepository::class);
        $authCodeRepository->revokeAuthCode(AppFixtures::AUTH_CODE_PRIVATE_CLIENT_IDENTIFIER);

        $authCodeRepository->isAuthCodeRevoked(AppFixtures::AUTH_CODE_PRIVATE_CLIENT_IDENTIFIER);

        $this->assertTrue($logs->hasError($this->reusedAuthCodeLogRecord(concurrent: false)));
    }

    public function testAuthCodeExchangedConcurrentlyIsLoggedAsError(): void
    {
        $logs = $this->captureLogs();
        $authCodeRepository = static::getContainer()->get(AuthCodeRepository::class);
        $authCodeRepository->isAuthCodeRevoked(AppFixtures::AUTH_CODE_PRIVATE_CLIENT_IDENTIFIER);
        $this->revokeAuthCodeInDatabase();

        try {
            $authCodeRepository->revokeAuthCode(AppFixtures::AUTH_CODE_PRIVATE_CLIENT_IDENTIFIER);
        } catch (OAuthServerException) {
            // The exchange fails, see testRevokingAuthCodeRevokedByConcurrentExchangeRevokesLinkedTokensAndFails.
        }

        $this->assertTrue($logs->hasError($this->reusedAuthCodeLogRecord(concurrent: true)));
    }

    public function testExchangeOfUnusedAuthCodeLogsNoError(): void
    {
        $logs = $this->captureLogs();
        $authCodeRepository = static::getContainer()->get(AuthCodeRepository::class);

        $this->assertFalse($authCodeRepository->isAuthCodeRevoked(AppFixtures::AUTH_CODE_PRIVATE_CLIENT_IDENTIFIER));
        $authCodeRepository->revokeAuthCode(AppFixtures::AUTH_CODE_PRIVATE_CLIENT_IDENTIFIER);

        $this->assertFalse($logs->hasErrorRecords());
    }

    private function captureLogs(): TestHandler
    {
        $logger = static::getContainer()->get(LoggerInterface::class);
        $this->assertInstanceOf(Logger::class, $logger);

        $logs = new TestHandler();
        $logger->pushHandler($logs);

        return $logs;
    }

    /** @return array{message: string, context: array<string, mixed>} */
    private function reusedAuthCodeLogRecord(bool $concurrent): array
    {
        return [
            'message' => 'Authorization code reused, the tokens issued for it were revoked.',
            'context' => [
                'auth_code_id' => AppFixtures::AUTH_CODE_PRIVATE_CLIENT_IDENTIFIER,
                'client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
                'user_id' => AppFixtures::USER_IDENTIFIER,
                'concurrent' => $concurrent,
            ],
        ];
    }

    /** Revokes the code without the entity manager, so its copy of the code stays unrevoked, as in another request. */
    private function revokeAuthCodeInDatabase(): void
    {
        static::getContainer()->get(EntityManagerInterface::class)->getConnection()->executeStatement(
            'UPDATE auth_code SET is_revoked = true WHERE identifier = :identifier',
            ['identifier' => AppFixtures::AUTH_CODE_PRIVATE_CLIENT_IDENTIFIER],
        );
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