<?php
declare(strict_types=1);

namespace App\Service\Api;

use App\Service\Api\DTO\GroupsResponse;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;

interface UserConnectorInterface
{
    public function getUserEntityByUserCredentials(
        $username, $password, $grantType, ClientEntityInterface $clientEntity): ?UserEntityInterface;

    public function getUserEntityById(string $id): ?UserEntityInterface;

    public function getGroups(string $id, int $limit): GroupsResponse;
}