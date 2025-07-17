<?php
declare(strict_types=1);

namespace App\LeagueOAuth2\Repository;

use App\Api\Contract\UserConnectorInterface;
use App\LeagueOAuth2\Entity\UserEntity;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly UserConnectorInterface $userConnector,
    )
    {
    }

    public function getUserEntityByUserCredentials(
        $username, $password, $grantType, ClientEntityInterface $clientEntity): ?UserEntityInterface
    {
        $user = $this->userConnector->getUserByUserCredentials($username, $password, $grantType, $clientEntity);
        if (!$user) return null; // authentication failure

        $model = new UserEntity();
        $model->setIdentifier($user->getId());

        return $model;
    }
}