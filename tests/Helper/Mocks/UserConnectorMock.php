<?php
declare(strict_types=1);

namespace App\Tests\Helper\Mocks;

use App\Api\Contract\UserConnectorInterface;
use App\Api\Contract\UserResponseInterface;
use App\Api\IdentityLink\Response\DbUserResponse;
use App\Api\IdentityLink\Response\GroupsResponse;
use App\DataFixtures\AppFixtures;
use League\OAuth2\Server\Entities\ClientEntityInterface;

class UserConnectorMock implements UserConnectorInterface
{
    private array $scopes = ['openid'];
    private bool $twoFaEnabled = true;
    private bool $userDeleted = false;

    /** @param list<string> $scopes */
    public function setScopes(array $scopes): void
    {
        $this->scopes = $scopes;
    }

    public function setTwoFaEnabled(bool $twoFaEnabled): void
    {
        $this->twoFaEnabled = $twoFaEnabled;
    }

    public function setUserDeleted(bool $userDeleted): void
    {
        $this->userDeleted = $userDeleted;
    }

    public function getUserByUserCredentials(
        $username, $password, $grantType, ClientEntityInterface $clientEntity): ?UserResponseInterface
    {
        if ($username === AppFixtures::USER_IDENTIFIER && $password === AppFixtures::USER_PASSWORD) {
            $user = new DbUserResponse();
            $user->setId(AppFixtures::USER_IDENTIFIER);
            $user->setTwoFaEnabled($this->twoFaEnabled);
            $user->setClaims([
                'name' => 'User Name',
                'preferred_username' => 'username',
                'email' => 'username@email.com',
                'email_verified' => true,
                'address' => 'User Address 11'
            ]);

            return $user;
        }

        return null;
    }

    public function getUserById(string $id): ?UserResponseInterface
    {
        if ($id === AppFixtures::USER_IDENTIFIER && !$this->userDeleted) {
            $user = new DbUserResponse();
            $user->setId(AppFixtures::USER_IDENTIFIER);
            $user->setTwoFaEnabled($this->twoFaEnabled);
            $user->setClaims([
                'name' => 'User Name',
                'preferred_username' => 'username',
                'email' => 'username@email.com',
                'email_verified' => true,
                'address' => 'User Address 11'
            ]);

            return $user;
        }

        return null;
    }

    public function getGroups(string $id, int $limit): GroupsResponse
    {
        $response = new GroupsResponse();
        $response->setGroups([]);

        return $response;
    }

    public function getScopes(string $id, string $audience): array
    {
        return $this->scopes;
    }
}
