<?php
declare(strict_types=1);

namespace App\LeagueOAuth2\Repository;

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
    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity)
    {
        $this->entityManager->persist($this->refreshTokenMapper->toDoctrineEntity($refreshTokenEntity));
        $this->entityManager->flush();
    }

    public function revokeRefreshToken($tokenId)
    {
        /** @var Repository $repository */
        $repository = $this->entityManager->getRepository(RefreshToken::class);
        $entity = $repository->getByIdentifier($tokenId);
        $entity->setIsRevoked(true);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function isRefreshTokenRevoked($tokenId): bool
    {
        /** @var Repository $repository */
        $repository = $this->entityManager->getRepository(RefreshToken::class);
        $entity = $repository->getByIdentifier($tokenId);

        return $entity->isRevoked();
    }
}