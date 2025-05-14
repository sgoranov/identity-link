<?php
declare(strict_types=1);

namespace App\Api\Contract;

use League\OAuth2\Server\Entities\ClientEntityInterface;

interface UserConnectorInterface
{
    public function getUserByUserCredentials(
        $username, $password, $grantType, ClientEntityInterface $clientEntity): ?UserResponseInterface;

    public function getUserById(string $id): ?UserResponseInterface;

    public function getGroups(string $id, int $limit): GroupsResponseInterface;
}