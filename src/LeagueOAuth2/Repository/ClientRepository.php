<?php
declare(strict_types=1);

namespace App\LeagueOAuth2\Repository;

use App\Api\Contract\ClientConnectorInterface;
use App\LeagueOAuth2\Entity\ClientEntity;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;

class ClientRepository implements ClientRepositoryInterface
{

    public function __construct(
        private readonly ClientConnectorInterface $clientConnector,
    )
    {
    }

    public function getClientEntity($clientIdentifier): ?ClientEntityInterface
    {
        $client = $this->clientConnector->getClientById($clientIdentifier);

        if (null === $client) {
            return null;
        }

        $model = new ClientEntity();
        $model->setIdentifier($client->getId());
        $model->setName($client->getName());
        $model->setRedirectUri($client->getRedirectUri());
        $model->setPublic($client->isPublic());
        $model->setScopes($client->getScopes());
        $model->setGrantTypes($client->getGrantTypes());

        return $model;
    }

    public function validateClient($clientIdentifier, $clientSecret, $grantType): bool
    {
        $client = $this->clientConnector->getClientByClientCredentials($clientIdentifier, $clientSecret, $grantType);

        return $client !== null;
    }
}