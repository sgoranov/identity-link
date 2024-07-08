<?php
declare(strict_types=1);

namespace App\Service\Api;

use App\Model\OAuth2\UserModel;
use App\Service\JwtTokenGenerator;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use Psr\Log\LoggerInterface;

class UserConnector extends AbstractConnector implements UserConnectorInterface
{
    private string $userAuthEndpoint;
    private string $userFetchEndpoint;

    public function __construct(
        private readonly JwtTokenGenerator $jwtTokenGenerator,
        private readonly LoggerInterface $logger,
    )
    {
        parent::__construct($this->jwtTokenGenerator, $this->logger);
    }
    public function setUserAuthEndpoint(string $userAuthEndpoint): void
    {
        $this->userAuthEndpoint = $userAuthEndpoint;
    }

    public function setUserFetchEndpoint(string $userFetchEndpoint): void
    {
        $this->userFetchEndpoint = $userFetchEndpoint;
    }

    public function getUserEntityById(string $id): ?UserEntityInterface
    {
        if (($content = $this->fetchData('GET', str_replace('{id}', $id, $this->userFetchEndpoint))) === null) {
            return null;
        }

        $data = json_decode($content, true);

        return $this->mapToUserModel($data['response']['user']);
    }

    public function getUserEntityByUserCredentials($username, $password, $grantType, ClientEntityInterface $clientEntity): ?UserEntityInterface
    {
        $options = [
            'json' => [
                'username' => $username,
                'password' => $password,
                'grantType' => $grantType,
            ]
        ];

        if (($content = $this->fetchData('POST', $this->userAuthEndpoint, $options)) === null) {
            return null;
        }

        $data = json_decode($content, true);

        return $this->mapToUserModel($data['response']['user']);
    }

    private function mapToUserModel(array $userData): UserModel
    {
        $user = new UserModel();
        $user->setIdentifier($userData['id']);
        $user->setRoles([]);
        $user->setIsTwoFaEnabled($userData['isTwoFaEnabled']);

        return $user;
    }
}