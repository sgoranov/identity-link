<?php
declare(strict_types=1);

namespace App\LeagueOAuth2\Entity\Mapper;

use App\Entity\AccessToken;
use App\LeagueOAuth2\Entity\AccessTokenEntity;

class AccessTokenMapper
{
    public function toDoctrineEntity(AccessTokenEntity $leagueEntity): AccessToken
    {
        $doctrineEntity = new AccessToken();
        $doctrineEntity->setScopes(json_encode($leagueEntity->getScopes()));
        $doctrineEntity->setIsRevoked($leagueEntity->isRevoked());
        $doctrineEntity->setIdentifier($leagueEntity->getIdentifier());
        $doctrineEntity->setExpiryDateTime($leagueEntity->getExpiryDateTime());
        $doctrineEntity->setClientIdentifier($leagueEntity->getClient()->getIdentifier());
        $doctrineEntity->setUserIdentifier($leagueEntity->getUserIdentifier());

        return $doctrineEntity;
    }
}