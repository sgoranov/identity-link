<?php
declare(strict_types=1);

namespace App\Service\Api;

use App\Model\OAuth2\ClientModel;
use App\Service\JwtTokenGenerator;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class ClientConnector extends AbstractConnector implements ClientConnectorInterface
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

    public function getClientEntityByClientCredentials($clientIdentifier, $clientSecret, $grantType): ?ClientEntityInterface
    {
        $options = [
            'json' => [
                'name' => $clientIdentifier,
                'secret' => $clientSecret,
                'grantType' => $grantType,
            ]
        ];

        if (($content = $this->fetchData('POST', $this->clientAuthEndpoint, $options)) === null) {
            return null;
        }

        $client = new ClientModel();

        $data = json_decode($content, true);

        $this->serializer->deserialize(json_encode($data['response']['client']), ClientModel::class, 'json', [
            AbstractNormalizer::OBJECT_TO_POPULATE => $client,
        ]);

        return $client;
    }

    public function getClientEntityById(string $id): ?ClientEntityInterface
    {
        $options = [
            'json' => [
                'type' => 'Client',
                'alias' => 't',
                'query' => 't.name = :clientIdentifier',
                'parameters' => [
                    'clientIdentifier' => $id,
                ],
                'limit' => 1
            ]
        ];

        if (($content = $this->fetchData('POST', $this->queryEndpoint, $options)) === null) {
            return null;
        }

        $client = new ClientModel();

        $data = json_decode($content, true);
        if (count($data['response']['result']) === 0) {
            return null;
        }

        $this->serializer->deserialize(json_encode($data['response']['result'][0]), ClientModel::class, 'json', [
            AbstractNormalizer::OBJECT_TO_POPULATE => $client,
        ]);

        return $client;
    }
}