<?php

namespace App\Service\Api;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;

interface UserConnectorInterface
{
    public function getUserEntityByUserCredentials(
        $username, $password, $grantType, ClientEntityInterface $clientEntity): ?UserEntityInterface;

    public function getUserEntityById(string $id): ?UserEntityInterface;
}