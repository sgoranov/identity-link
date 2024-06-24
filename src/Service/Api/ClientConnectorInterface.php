<?php
declare(strict_types=1);

namespace App\Service\Api;

use League\OAuth2\Server\Entities\ClientEntityInterface;

interface ClientConnectorInterface
{
    public function getClientEntityByClientCredentials($clientIdentifier, $clientSecret, $grantType): ?ClientEntityInterface;

    public function getClientEntityById(string $id): ?ClientEntityInterface;
}