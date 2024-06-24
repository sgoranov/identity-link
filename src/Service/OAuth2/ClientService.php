<?php
declare(strict_types=1);

namespace App\Service\OAuth2;

use App\Service\Api\ClientConnectorInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;

class ClientService implements ClientRepositoryInterface
{

    public function __construct(
        private readonly ClientConnectorInterface $clientConnector,
    )
    {
    }

    public function getClientEntity($clientIdentifier): ?ClientEntityInterface
    {
        return $this->clientConnector->getClientEntityById($clientIdentifier);
    }

    public function validateClient($clientIdentifier, $clientSecret, $grantType): bool
    {
        $client = $this->clientConnector->getClientEntityByClientCredentials($clientIdentifier, $clientSecret, $grantType);

        return $client !== null;
    }
}