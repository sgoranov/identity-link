<?php
declare(strict_types=1);

namespace App\LeagueOAuth2\Entity\Mapper;

use App\Api\Contract\ClientResponseInterface;
use App\LeagueOAuth2\Entity\ClientEntity;

class ClientMapper
{
    public function toLeagueEntity(ClientResponseInterface $leagueEntity): ClientEntity
    {
        $client = new ClientEntity();
        $client->setIdentifier($leagueEntity->getId());
        $client->setName($leagueEntity->getName());
        $client->setRedirectUri($leagueEntity->getRedirectUri());
        $client->setPublic($leagueEntity->isPublic());
        $client->setAudience($leagueEntity->getAudience());
        $client->setScopes($leagueEntity->getScopes());
        $client->setGrantTypes($leagueEntity->getGrantTypes());

        return $client;

    }
}
