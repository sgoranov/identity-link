<?php
declare(strict_types=1);

namespace App\Api\IdentityLink\Connector;

use App\Api\Contract\GroupsResponseInterface;
use App\Api\Contract\UserConnectorInterface;
use App\Api\IdentityLink\Response\DbUserResponse;
use App\Api\IdentityLink\Response\GroupsResponse;
use App\Api\Shared\AbstractConnector;
use App\Service\JwtTokenGenerator;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class DbUserConnector extends AbstractConnector implements UserConnectorInterface
{
    private string $queryEndpoint;
    private string $userAuthEndpoint;
    private string $userFetchEndpoint;

    public function __construct(
        private readonly JwtTokenGenerator $jwtTokenGenerator,
        private readonly LoggerInterface $logger,
        private readonly SerializerInterface $serializer,
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

    public function setQueryEndpoint(string $queryEndpoint): void
    {
        $this->queryEndpoint = $queryEndpoint;
    }

    public function getUserById(string $id): ?DbUserResponse
    {
        if (($content = $this->fetchData('GET', str_replace('{id}', $id, $this->userFetchEndpoint))) === null) {
            return null;
        }

        $data = json_decode($content, true);

        $response = new DbUserResponse();
        $this->serializer->deserialize(json_encode($data['response']['user']), DbUserResponse::class, 'json', [
            AbstractNormalizer::OBJECT_TO_POPULATE => $response,
        ]);

        return $response;
    }

    public function getUserByUserCredentials($username, $password, $grantType, ClientEntityInterface $clientEntity): ?DbUserResponse
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

        $response = new DbUserResponse();
        $this->serializer->deserialize(json_encode($data['response']['user']), DbUserResponse::class, 'json', [
            AbstractNormalizer::OBJECT_TO_POPULATE => $response,
        ]);

        return $response;
    }

    public function getGroups(string $id, int $limit): GroupsResponseInterface
    {
        $options = [
            'json' => [
                'alias' => 't',
                'type' => 'Group',
                'joins' => [
                    'u' => 't.users'
                ],
                'query' => 'u.id = :id',
                'parameters' => [
                    'id' => $id,
                ],
                'limit' => $limit,
            ]
        ];

        if (($content = $this->fetchData('POST', $this->queryEndpoint, $options)) === null) {
            throw new \Exception('Unable to fetch user groups');
        }

        $data = json_decode($content, true);

        $response = new GroupsResponse();
        $this->serializer->deserialize(json_encode($data['response']), GroupsResponse::class, 'json', [
            AbstractNormalizer::OBJECT_TO_POPULATE => $response,
        ]);

        return $response;
    }
}