<?php
declare(strict_types=1);

namespace App\Tests\Mock;

use App\Api\Contract\UserConnectorInterface;
use App\Api\IdentityLink\Response\GroupsResponse;
use App\DataFixtures\AppFixtures;
use App\LeagueOAuth2\Entity\UserEntity;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;

class UserConnectorMock implements UserConnectorInterface
{

    public function getUserByUserCredentials(
        $username, $password, $grantType, ClientEntityInterface $clientEntity): ?\App\Api\Contract\UserResponseInterface
    {
        if ($username === AppFixtures::USER_IDENTIFIER && $password === AppFixtures::USER_PASSWORD) {
            return $this->getUser();
        }

        return null;
    }

    public function getUserById(string $id): ?\App\Api\Contract\UserResponseInterface
    {
        if ($id === AppFixtures::USER_IDENTIFIER) {
            return $this->getUser();
        }

        return null;
    }

    private function getUser(): UserEntityInterface
    {
        $user = new UserEntity();
        $user->setIdentifier(AppFixtures::USER_IDENTIFIER);

        return $user;
    }

    public function getGroups(string $id, int $limit): GroupsResponse
    {
        $response = new GroupsResponse();
        $response->setGroups([]);

        return $response;
    }
}