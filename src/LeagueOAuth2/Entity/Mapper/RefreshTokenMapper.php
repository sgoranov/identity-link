<?php
declare(strict_types=1);

namespace App\LeagueOAuth2\Entity\Mapper;

use App\Entity\RefreshToken;
use App\LeagueOAuth2\Entity\RefreshTokenEntity;
use App\Repository\AccessTokenRepository;

class RefreshTokenMapper
{
    public function __construct(
        readonly AccessTokenMapper $accessTokenMapper,
        readonly AccessTokenRepository $accessTokenRepository,
    )
    {
    }

    public function toDoctrineEntity(RefreshTokenEntity $leagueEntity): RefreshToken
    {
        $doctrineAccessTokenEntity = $this->accessTokenRepository->findOneBy(['identifier' => $leagueEntity->getAccessToken()->getIdentifier()]);
        if ($doctrineAccessTokenEntity === null) {
            $doctrineAccessTokenEntity = $this->accessTokenMapper->toDoctrineEntity($leagueEntity->getAccessToken());
        }

        $doctrineEntity = new RefreshToken();
        $doctrineEntity->setIsRevoked($leagueEntity->isRevoked());
        $doctrineEntity->setIdentifier($leagueEntity->getIdentifier());
        $doctrineEntity->setExpiryDateTime($leagueEntity->getExpiryDateTime());
        $doctrineEntity->setAccessToken($doctrineAccessTokenEntity);

        return $doctrineEntity;
    }
}