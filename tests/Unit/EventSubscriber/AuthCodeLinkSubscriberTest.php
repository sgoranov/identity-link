<?php
declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\AccessToken;
use App\EventSubscriber\AuthCodeLinkSubscriber;
use App\LeagueOAuth2\Entity\AccessTokenEntity;
use App\Repository\AccessTokenRepository;
use App\Security\EncryptionKeyLoader;
use App\Tests\Helper\TestHelper;
use Defuse\Crypto\Crypto;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Server\RequestAccessTokenEvent;
use League\OAuth2\Server\RequestEvent;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\MockObject\Rule\InvocationOrder;
use PHPUnit\Framework\TestCase;

class AuthCodeLinkSubscriberTest extends TestCase
{
    private const AUTH_CODE_ID = 'auth-code-id';
    private const ISSUED_TOKEN_ID = 'issued-access-token-id';
    private const REPLACED_TOKEN_ID = 'replaced-access-token-id';

    public function testLinksTokenFromAuthorizationCodeGrantToTheCode(): void
    {
        $issuedToken = $this->createStoredToken(self::ISSUED_TOKEN_ID);
        $repository = $this->createMock(AccessTokenRepository::class);
        $repository->expects($this->once())->method('getByIdentifier')->with(self::ISSUED_TOKEN_ID)->willReturn($issuedToken);

        $this->createSubscriber($repository, $this->expectFlush($this->once()))->onAccessTokenIssued($this->createEvent([
            'grant_type' => 'authorization_code',
            'code' => $this->encrypt(['auth_code_id' => self::AUTH_CODE_ID]),
        ]));

        $this->assertSame(self::AUTH_CODE_ID, $issuedToken->getAuthCodeIdentifier());
    }

    public function testRefreshedTokenInheritsTheLinkOfTheTokenItReplaces(): void
    {
        $issuedToken = $this->createStoredToken(self::ISSUED_TOKEN_ID);
        $repository = $this->createMock(AccessTokenRepository::class);
        $repository->method('getByIdentifier')->willReturnMap([
            [self::REPLACED_TOKEN_ID, $this->createStoredToken(self::REPLACED_TOKEN_ID, self::AUTH_CODE_ID)],
            [self::ISSUED_TOKEN_ID, $issuedToken],
        ]);

        $this->createSubscriber($repository, $this->expectFlush($this->once()))->onAccessTokenIssued($this->createEvent([
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->encrypt(['access_token_id' => self::REPLACED_TOKEN_ID]),
        ]));

        $this->assertSame(self::AUTH_CODE_ID, $issuedToken->getAuthCodeIdentifier());
    }

    public function testRefreshedTokenStaysUnlinkedWhenTheTokenItReplacesIsUnlinked(): void
    {
        // For example a token from the password grant, which involves no authorization code.
        $repository = $this->createMock(AccessTokenRepository::class);
        $repository->expects($this->once())
            ->method('getByIdentifier')
            ->with(self::REPLACED_TOKEN_ID)
            ->willReturn($this->createStoredToken(self::REPLACED_TOKEN_ID));

        $this->createSubscriber($repository, $this->expectFlush($this->never()))->onAccessTokenIssued($this->createEvent([
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->encrypt(['access_token_id' => self::REPLACED_TOKEN_ID]),
        ]));
    }

    public function testIgnoresGrantsWithoutAuthorizationCode(): void
    {
        $repository = $this->createMock(AccessTokenRepository::class);
        $repository->expects($this->never())->method('getByIdentifier');

        $this->createSubscriber($repository, $this->expectFlush($this->never()))->onAccessTokenIssued($this->createEvent([
            'grant_type' => 'client_credentials',
        ]));
    }

    private function createSubscriber(
        AccessTokenRepository $repository,
        EntityManagerInterface $entityManager,
    ): AuthCodeLinkSubscriber {
        return new AuthCodeLinkSubscriber($repository, $this->createEncryptionKeyLoader(), $entityManager);
    }

    private function expectFlush(InvocationOrder $times): EntityManagerInterface
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($times)->method('flush');

        return $entityManager;
    }

    /** @param array<string, string> $parameters the token request's body */
    private function createEvent(array $parameters): RequestAccessTokenEvent
    {
        $issuedToken = new AccessTokenEntity();
        $issuedToken->setIdentifier(self::ISSUED_TOKEN_ID);

        return new RequestAccessTokenEvent(
            RequestEvent::ACCESS_TOKEN_ISSUED,
            (new ServerRequest('POST', '/oauth2/token'))->withParsedBody($parameters),
            $issuedToken,
        );
    }

    private function createStoredToken(string $identifier, ?string $authCodeIdentifier = null): AccessToken
    {
        $storedToken = new AccessToken();
        $storedToken->setIdentifier($identifier);
        $storedToken->setAuthCodeIdentifier($authCodeIdentifier);

        return $storedToken;
    }

    /** @param array<string, string> $payload */
    private function encrypt(array $payload): string
    {
        return Crypto::encrypt(
            json_encode($payload, JSON_THROW_ON_ERROR),
            $this->createEncryptionKeyLoader()->loadEncryptionKey(),
        );
    }

    private function createEncryptionKeyLoader(): EncryptionKeyLoader
    {
        $encryptionKeyLoader = new EncryptionKeyLoader();
        $encryptionKeyLoader->setEncryptionKeyPath(TestHelper::ENCRYPTION_KEY_PATH);

        return $encryptionKeyLoader;
    }
}
