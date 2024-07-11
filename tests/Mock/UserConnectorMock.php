<?php
declare(strict_types=1);

namespace App\Tests\Mock;

use App\DataFixtures\AppFixtures;
use App\Model\OAuth2\UserModel;
use App\Service\Api\DTO\GroupsResponse;
use App\Service\Api\UserConnectorInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;

class UserConnectorMock implements UserConnectorInterface
{

    public function getUserEntityByUserCredentials(
        $username, $password, $grantType, ClientEntityInterface $clientEntity): ?UserEntityInterface
    {
        if ($username === AppFixtures::USER_IDENTIFIER && $password === AppFixtures::USER_PASSWORD) {
            return $this->getUser();
        }

        return null;
    }

    public function getUserEntityById(string $id): ?UserEntityInterface
    {
        if ($id === AppFixtures::USER_IDENTIFIER) {
            return $this->getUser();
        }

        return null;
    }

    private function getUser(): UserEntityInterface
    {
        $user = new UserModel();
        $user->setRoles([]);
        $user->setIdentifier(AppFixtures::USER_IDENTIFIER);

        return $user;
    }

    public function getGroups(string $id, int $limit): GroupsResponse
    {
        $response = new GroupsResponse();
        $response->setGroups([]);
        $response->setHasMore(false);

        return $response;
    }
}