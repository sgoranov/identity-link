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
    public function __construct(
        private readonly string $queryEndpoint,
        private readonly string $userAuthEndpoint,
        private readonly string $userFetchEndpoint,
        private readonly string $scopeEndpoint,
        private readonly JwtTokenGenerator $jwtTokenGenerator,
        private readonly LoggerInterface $logger,
        private readonly SerializerInterface $serializer,
    )
    {
        parent::__construct($this->jwtTokenGenerator, $this->logger);
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

        $response->setClaims([
            'name' => sprintf('%s %s', $data['response']['user']['firstName'], $data['response']['user']['lastName']),
            'preferred_username' => $data['response']['user']['username'],
            'given_name' => $data['response']['user']['firstName'],
            'family_name' => $data['response']['user']['lastName'],
            'email' => $data['response']['user']['email'],
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

    public function getScopes(string $id, string $audience): array
    {
        $endpoint = str_replace('{id}', rawurlencode($id), $this->scopeEndpoint);
        $url = $endpoint . '?' . http_build_query(['audience' => $audience]);

        if (($content = $this->fetchData('GET', $url)) === null) {
            throw new \Exception('Unable to fetch user scopes');
        }

        $data = json_decode($content, true);

        return $data['response']['scopes'] ?? [];
    }
}
