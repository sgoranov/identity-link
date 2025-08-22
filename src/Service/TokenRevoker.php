<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\AccessTokenRepository;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

class TokenRevoker
{
    public function __construct(
        private readonly AccessTokenRepository $accessTokenRepository,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly TokenValidator $tokenValidator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function revokeAllTokensForUser(string $userIdentifier): void
    {
        $this->refreshTokenRepository->revokeByUserIdentifier($userIdentifier);
        $this->accessTokenRepository->revokeByUserIdentifier($userIdentifier);
        $this->entityManager->flush();
    }

    public function revokeToken(string $token, ?string $clientId = null): void
    {
        $result = $this->tokenValidator->validateToken($token, $clientId);
        if ($result === null) {
            return;
        }

        if ($result->getType() === TokenType::ACCESS) {
            $this->revokeAccessTokenByIdentifier($result->getEntity()->getIdentifier());
        } else {
            $this->revokeRefreshTokenByIdentifier($result->getEntity()->getIdentifier());
        }
    }

    public function revokeAccessTokenByIdentifier(string $identifier): void
    {
        $token = $this->accessTokenRepository->getByIdentifier($identifier);
        if ($token === null) {
            return;
        }

        $token->setIsRevoked(true);
        $this->entityManager->flush();
    }

    public function revokeRefreshTokenByIdentifier(string $identifier): void
    {
        $refreshToken = $this->refreshTokenRepository->getByIdentifier($identifier);
        if ($refreshToken === null) {
            return;
        }

        $accessToken = $refreshToken->getAccessToken();
        $accessToken->setIsRevoked(true);
        $refreshToken->setIsRevoked(true);
        $this->entityManager->flush();
    }
}