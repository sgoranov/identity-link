<?php
declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\AccessToken;
use App\Repository\AccessTokenRepository;
use App\Security\AccessTokenHandler;
use PHPUnit\Framework\TestCase;
use sgoranov\IdentityLinkShared\Security\AccessTokenHandler as SharedAccessTokenHandler;
use sgoranov\IdentityLinkShared\Security\User;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class AccessTokenHandlerTest extends TestCase
{
    private const JWT = 'header.payload.signature';
    private const JTI = '6fbc4538-e365-479b-84d9-c881f3259c3f';

    public function testAcceptsStoredAccessTokenThatIsNotRevoked(): void
    {
        $userBadge = $this->createUserBadge(['sub' => 'user-id', 'jti' => self::JTI]);
        $handler = new AccessTokenHandler(
            $this->createSharedHandler($userBadge),
            $this->createRepositoryReturning($this->createStoredToken(isRevoked: false)),
        );

        $this->assertSame($userBadge, $handler->getUserBadgeFrom(self::JWT));
    }

    public function testRejectsRevokedAccessToken(): void
    {
        $handler = new AccessTokenHandler(
            $this->createSharedHandler($this->createUserBadge(['sub' => 'user-id', 'jti' => self::JTI])),
            $this->createRepositoryReturning($this->createStoredToken(isRevoked: true)),
        );

        $this->expectException(BadCredentialsException::class);
        $handler->getUserBadgeFrom(self::JWT);
    }

    public function testRejectsAccessTokenThatIsNotStored(): void
    {
        $handler = new AccessTokenHandler(
            $this->createSharedHandler($this->createUserBadge(['sub' => 'user-id', 'jti' => self::JTI])),
            $this->createRepositoryReturning(null),
        );

        $this->expectException(BadCredentialsException::class);
        $handler->getUserBadgeFrom(self::JWT);
    }

    public function testRejectsAccessTokenWithNonStringJti(): void
    {
        $repository = $this->createMock(AccessTokenRepository::class);
        $repository->expects($this->never())->method('getByIdentifier');
        $handler = new AccessTokenHandler(
            $this->createSharedHandler($this->createUserBadge(['sub' => 'user-id', 'jti' => 42])),
            $repository,
        );

        $this->expectException(BadCredentialsException::class);
        $handler->getUserBadgeFrom(self::JWT);
    }

    public function testAcceptsInternalTokenWithoutJtiWithoutLookingItUp(): void
    {
        $userBadge = $this->createUserBadge(['sub' => 'internal']);
        $repository = $this->createMock(AccessTokenRepository::class);
        $repository->expects($this->never())->method('getByIdentifier');
        $handler = new AccessTokenHandler($this->createSharedHandler($userBadge), $repository);

        $this->assertSame($userBadge, $handler->getUserBadgeFrom(self::JWT));
    }

    public function testLeavesTokensRejectedByTheSharedHandlerRejected(): void
    {
        $sharedHandler = $this->createMock(SharedAccessTokenHandler::class);
        $sharedHandler->method('getUserBadgeFrom')->willThrowException(new BadCredentialsException('JWT aud is not valid.'));
        $repository = $this->createMock(AccessTokenRepository::class);
        $repository->expects($this->never())->method('getByIdentifier');
        $handler = new AccessTokenHandler($sharedHandler, $repository);

        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('JWT aud is not valid.');
        $handler->getUserBadgeFrom(self::JWT);
    }

    /** @param array<string, mixed> $claims the decoded claims of the access token */
    private function createUserBadge(array $claims): UserBadge
    {
        return new UserBadge($claims['sub'], static function (string $userIdentifier) use ($claims): User {
            $user = new User($userIdentifier, []);
            $user->setAccessToken((object) $claims);

            return $user;
        });
    }

    private function createSharedHandler(UserBadge $userBadge): SharedAccessTokenHandler
    {
        $sharedHandler = $this->createMock(SharedAccessTokenHandler::class);
        $sharedHandler->method('getUserBadgeFrom')->with(self::JWT)->willReturn($userBadge);

        return $sharedHandler;
    }

    private function createRepositoryReturning(?AccessToken $storedToken): AccessTokenRepository
    {
        $repository = $this->createMock(AccessTokenRepository::class);
        $repository->expects($this->once())->method('getByIdentifier')->with(self::JTI)->willReturn($storedToken);

        return $repository;
    }

    private function createStoredToken(bool $isRevoked): AccessToken
    {
        $storedToken = new AccessToken();
        $storedToken->setIdentifier(self::JTI);
        $storedToken->setIsRevoked($isRevoked);

        return $storedToken;
    }
}
