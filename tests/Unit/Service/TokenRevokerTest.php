<?php
declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\AccessToken;
use App\Entity\RefreshToken;
use App\Repository\AccessTokenRepository;
use App\Repository\RefreshTokenRepository;
use App\Service\TokenRevoker;
use App\Service\TokenType;
use App\Service\TokenValidationResult;
use App\Service\TokenValidator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class TokenRevokerTest extends TestCase
{
    private AccessTokenRepository $accessTokenRepository;
    private RefreshTokenRepository $refreshTokenRepository;
    private TokenValidator $tokenValidator;
    private TokenRevoker $tokenRevoker;

    protected function setUp(): void
    {
        $this->accessTokenRepository = $this->createMock(AccessTokenRepository::class);
        $this->refreshTokenRepository = $this->createMock(RefreshTokenRepository::class);
        $this->tokenValidator = $this->createMock(TokenValidator::class);

        $this->tokenRevoker = new TokenRevoker(
            $this->accessTokenRepository,
            $this->refreshTokenRepository,
            $this->tokenValidator,
            $this->createMock(EntityManagerInterface::class),
        );
    }

    public function testRevokeAllTokensForUserRevokesRefreshAndAccessTokensAndFlushesOnce(): void
    {
        $this->accessTokenRepository
            ->expects($this->once())
            ->method('revokeByUserIdentifier')
            ->with('user-id');

        $this->refreshTokenRepository
            ->expects($this->once())
            ->method('revokeByUserIdentifier')
            ->with('user-id');

        $this->expectTokenValidationNever();

        $this->tokenRevoker->revokeAllTokensForUser('user-id');
    }

    public function testRevokeTokenDoesNothingWhenTokenIsInvalid(): void
    {
        $this->tokenValidator
            ->expects($this->once())
            ->method('validateToken')
            ->with('invalid-token', 'client-id')
            ->willReturn(null);
        $this->expectAccessTokenLookupNever();
        $this->expectRefreshTokenLookupNever();

        $this->tokenRevoker->revokeToken('invalid-token', 'client-id');
    }

    public function testRevokeTokenRevokesAccessTokenFromValidationResult(): void
    {
        $accessToken = $this->createAccessToken('access-token-id');
        $this->expectTokenValidationReturning(
            new TokenValidationResult($accessToken, [], TokenType::ACCESS),
        );

        $this->accessTokenRepository
            ->expects($this->once())
            ->method('getByIdentifier')
            ->with('access-token-id')
            ->willReturn($accessToken);
        $this->expectRefreshTokenLookupNever();

        $this->tokenRevoker->revokeToken('access-token', 'client-id');

        $this->assertTrue($accessToken->isRevoked());
    }

    public function testRevokeTokenRevokesRefreshTokenAndItsAccessTokenFromValidationResult(): void
    {
        $accessToken = $this->createAccessToken('access-token-id');
        $refreshToken = $this->createRefreshToken('refresh-token-id', $accessToken);
        $this->expectTokenValidationReturning(
            new TokenValidationResult($refreshToken, [], TokenType::REFRESH),
        );

        $this->refreshTokenRepository
            ->expects($this->once())
            ->method('getByIdentifier')
            ->with('refresh-token-id')
            ->willReturn($refreshToken);
        $this->expectAccessTokenLookupNever();

        $this->tokenRevoker->revokeToken('refresh-token', 'client-id');

        $this->assertTrue($refreshToken->isRevoked());
        $this->assertTrue($accessToken->isRevoked());
    }

    public function testRevokeAccessTokenByIdentifierDoesNothingWhenTokenDoesNotExist(): void
    {
        $this->accessTokenRepository
            ->expects($this->once())
            ->method('getByIdentifier')
            ->with('missing-access-token-id')
            ->willReturn(null);
        $this->expectRefreshTokenLookupNever();
        $this->expectTokenValidationNever();

        $this->tokenRevoker->revokeAccessTokenByIdentifier('missing-access-token-id');
    }

    public function testRevokeAccessTokenByIdentifierRevokesExistingToken(): void
    {
        $accessToken = $this->createAccessToken('access-token-id');
        $this->accessTokenRepository
            ->expects($this->once())
            ->method('getByIdentifier')
            ->with('access-token-id')
            ->willReturn($accessToken);
        $this->expectRefreshTokenLookupNever();
        $this->expectTokenValidationNever();

        $this->tokenRevoker->revokeAccessTokenByIdentifier('access-token-id');

        $this->assertTrue($accessToken->isRevoked());
    }

    public function testRevokeRefreshTokenByIdentifierDoesNothingWhenTokenDoesNotExist(): void
    {
        $this->refreshTokenRepository
            ->expects($this->once())
            ->method('getByIdentifier')
            ->with('missing-refresh-token-id')
            ->willReturn(null);
        $this->expectAccessTokenLookupNever();
        $this->expectTokenValidationNever();

        $this->tokenRevoker->revokeRefreshTokenByIdentifier('missing-refresh-token-id');
    }

    public function testRevokeRefreshTokenByIdentifierRevokesRefreshTokenAndItsAccessToken(): void
    {
        $accessToken = $this->createAccessToken('access-token-id');
        $refreshToken = $this->createRefreshToken('refresh-token-id', $accessToken);

        $this->refreshTokenRepository
            ->expects($this->once())
            ->method('getByIdentifier')
            ->with('refresh-token-id')
            ->willReturn($refreshToken);
        $this->expectAccessTokenLookupNever();
        $this->expectTokenValidationNever();

        $this->tokenRevoker->revokeRefreshTokenByIdentifier('refresh-token-id');

        $this->assertTrue($refreshToken->isRevoked());
        $this->assertTrue($accessToken->isRevoked());
    }

    private function expectTokenValidationReturning(TokenValidationResult $result): void
    {
        $this->tokenValidator
            ->expects($this->once())
            ->method('validateToken')
            ->with($this->isType('string'), 'client-id')
            ->willReturn($result);
    }

    private function expectTokenValidationNever(): void
    {
        $this->tokenValidator
            ->expects($this->never())
            ->method('validateToken');
    }

    private function expectAccessTokenLookupNever(): void
    {
        $this->accessTokenRepository
            ->expects($this->never())
            ->method('getByIdentifier');
    }

    private function expectRefreshTokenLookupNever(): void
    {
        $this->refreshTokenRepository
            ->expects($this->never())
            ->method('getByIdentifier');
    }

    private function createAccessToken(string $identifier): AccessToken
    {
        $accessToken = new AccessToken();
        $accessToken->setIdentifier($identifier);

        return $accessToken;
    }

    private function createRefreshToken(string $identifier, AccessToken $accessToken): RefreshToken
    {
        $refreshToken = new RefreshToken();
        $refreshToken->setIdentifier($identifier);
        $refreshToken->setAccessToken($accessToken);

        return $refreshToken;
    }
}
