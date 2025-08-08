<?php
declare(strict_types=1);

namespace App\Api\IdentityLink\Connector;

use App\Api\Contract\ClientConnectorInterface;
use App\Api\Contract\ClientResponseInterface;
use App\Api\Contract\GroupsResponseInterface;
use App\Api\IdentityLink\Response\DbClientResponse;
use App\Api\IdentityLink\Response\GroupsResponse;
use App\Api\Shared\AbstractConnector;
use App\Service\JwtTokenGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class DbClientConnector extends AbstractConnector implements ClientConnectorInterface
{
    private string $queryEndpoint;
    private string $clientAuthEndpoint;

    public function __construct(
        private readonly JwtTokenGenerator $jwtTokenGenerator,
        private readonly LoggerInterface $logger,
        private readonly SerializerInterface $serializer,
    )
    {
        parent::__construct($this->jwtTokenGenerator, $this->logger);
    }

    public function setClientAuthEndpoint(string $clientAuthEndpoint): void
    {
        $this->clientAuthEndpoint = $clientAuthEndpoint;
    }

    public function setQueryEndpoint(string $queryEndpoint): void
    {
        $this->queryEndpoint = $queryEndpoint;
    }

    public function getClientByClientCredentials($clientIdentifier, $clientSecret, $grantType): ?ClientResponseInterface
    {
        $options = [
            'json' => [
                'id' => $clientIdentifier,
                'secret' => $clientSecret,
                'grantType' => $grantType,
            ]
        ];

        if (($content = $this->fetchData('POST', $this->clientAuthEndpoint, $options)) === null) {
            return null;
        }

        $data = json_decode($content, true);

        $response = new DbClientResponse();
        $this->serializer->deserialize(json_encode($data['response']['client']), DbClientResponse::class, 'json', [
            AbstractNormalizer::OBJECT_TO_POPULATE => $response,
        ]);

        return $response;
    }

    public function getClientById(string $id): ?ClientResponseInterface
    {
        $options = [
            'json' => [
                'type' => 'Client',
                'alias' => 't',
                'query' => 't.id = :clientIdentifier',
                'parameters' => [
                    'clientIdentifier' => $id,
                ],
                'limit' => 1
            ]
        ];

        if (($content = $this->fetchData('POST', $this->queryEndpoint, $options)) === null) {
            return null;
        }

        $data = json_decode($content, true);
        if (count($data['response']['result']) === 0) {
            return null;
        }

        $response = new DbClientResponse();
        $this->serializer->deserialize(json_encode($data['response']['result'][0]), DbClientResponse::class, 'json', [
            AbstractNormalizer::OBJECT_TO_POPULATE => $response,
        ]);

        return $response;
    }

    public function getGroups(string $uuid, int $limit): GroupsResponseInterface
    {
        $options = [
            'json' => [
                'alias' => 't',
                'type' => 'Group',
                'joins' => [
                    'c' => 't.clients'
                ],
                'query' => 'c.id = :id',
                'parameters' => [
                    'id' => $uuid,
                ],
                'limit' => $limit,
            ]
        ];

        if (($content = $this->fetchData('POST', $this->queryEndpoint, $options)) === null) {
            throw new \Exception('Unable to fetch client groups');
        }

        $data = json_decode($content, true);

        $response = new GroupsResponse();
        $this->serializer->deserialize(json_encode($data['response']), GroupsResponse::class, 'json', [
            AbstractNormalizer::OBJECT_TO_POPULATE => $response,
        ]);

        return $response;
    }
}