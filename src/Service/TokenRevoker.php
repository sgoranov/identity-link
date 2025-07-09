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
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function revokeByUserIdentifier(string $userIdentifier): void
    {
        $this->refreshTokenRepository->revokeByUserIdentifier($userIdentifier);
        $this->accessTokenRepository->revokeByUserIdentifier($userIdentifier);
        $this->entityManager->flush();
    }

    public function revokeByTokenIdentifier(string $tokenIdentifier): void
    {
        $token = $this->accessTokenRepository->getByIdentifier($tokenIdentifier);
        if ($token !== null) {
            $token->setIsRevoked(true);
            $this->entityManager->flush();
        }
    }
}