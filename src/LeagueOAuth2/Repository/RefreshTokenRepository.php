<?php
declare(strict_types=1);

namespace App\LeagueOAuth2\Repository;

use App\Api\Contract\UserConnectorInterface;
use App\Entity\RefreshToken;
use App\LeagueOAuth2\Entity\Mapper\RefreshTokenMapper;
use App\LeagueOAuth2\Entity\RefreshTokenEntity;
use App\Repository\RefreshTokenRepository as Repository;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;

class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function __construct(
        readonly EntityManagerInterface $entityManager,
        readonly RefreshTokenMapper $refreshTokenMapper,
        private readonly UserConnectorInterface $userConnector,
    )
    {
    }

    public function getNewRefreshToken(): RefreshTokenEntity
    {
        $token = new RefreshTokenEntity();
        $token->setIsRevoked(false);

        return $token;
    }

    /**
     * @param RefreshTokenEntity $refreshTokenEntity
     * @return void
     */
    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        $userIdentifier = $refreshTokenEntity->getAccessToken()->getUserIdentifier();
        $doctrineEntity = $this->refreshTokenMapper->toDoctrineEntity($refreshTokenEntity);
        $doctrineEntity->setUserIdentifier($userIdentifier);

        $this->entityManager->persist($doctrineEntity);
        $this->entityManager->flush();
    }

    public function revokeRefreshToken(string $tokenId): void
    {
        /** @var Repository $repository */
        $repository = $this->entityManager->getRepository(RefreshToken::class);
        $entity = $repository->getByIdentifier($tokenId);
        $entity->setIsRevoked(true);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function isRefreshTokenRevoked(string $tokenId): bool
    {
        /** @var Repository $repository */
        $repository = $this->entityManager->getRepository(RefreshToken::class);
        $entity = $repository->getByIdentifier($tokenId);
        if ($entity->isRevoked()) {
            return true;
        }

        // Every user may sign in, so deleting the user is how their access is removed. League calls this only from
        // the refresh token grant, before it issues anything, so a deleted user's refresh token counts as revoked.
        return $this->userConnector->getUserById($entity->getUserIdentifier()) === null;
    }
}
