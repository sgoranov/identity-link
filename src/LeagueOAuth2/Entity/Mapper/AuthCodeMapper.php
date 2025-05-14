<?php
declare(strict_types=1);

namespace App\LeagueOAuth2\Entity\Mapper;

use App\Entity\AuthCode;
use App\LeagueOAuth2\Entity\AuthCodeEntity;

class AuthCodeMapper
{

    public function toDoctrineEntity(AuthCodeEntity $leagueEntity): AuthCode
    {
        $doctrineEntity = new AuthCode();
        $doctrineEntity->setIdentifier($leagueEntity->getIdentifier());
        $doctrineEntity->setScopes(json_encode($leagueEntity->getScopes()));
        $doctrineEntity->setExpiryDateTime($leagueEntity->getExpiryDateTime());
        $doctrineEntity->setRedirectUri($leagueEntity->getRedirectUri());
        $doctrineEntity->setUserIdentifier($leagueEntity->getUserIdentifier());
        $doctrineEntity->setClientIdentifier($leagueEntity->getClient()->getIdentifier());
        $doctrineEntity->setIsRevoked($leagueEntity->isRevoked());

        return $doctrineEntity;
    }
}