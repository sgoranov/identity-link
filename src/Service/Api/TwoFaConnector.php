<?php
declare(strict_types=1);

namespace App\Service\Api;

use App\Service\Api\DTO\AuthResponse;
use App\Service\JwtTokenGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class TwoFaConnector extends AbstractConnector implements TwoFaConnectorInterface
{
    private string $authEndpoint;

    public function __construct(
        private readonly JwtTokenGenerator $jwtTokenGenerator,
        private readonly LoggerInterface $logger,
        private readonly SerializerInterface $serializer,
    )
    {
        parent::__construct($this->jwtTokenGenerator, $this->logger);
    }

    public function initiateAuthenticationRequest(string $userIdentifier): ?string
    {
        $options = [
            'json' => [
                'identifier' => $userIdentifier,
            ]
        ];

        if (($content = $this->fetchData('POST', $this->authEndpoint, $options)) === null) {
            return null;
        }

        $auth = new AuthResponse();

        $data = json_decode($content, true);

        $this->serializer->deserialize(json_encode($data['response']['auth']), AuthResponse::class, 'json', [
            AbstractNormalizer::OBJECT_TO_POPULATE => $auth,
        ]);

        return $auth->getId();
    }

    public function validateAuthenticationRequest(string $id): bool
    {
        $endpoint = $this->authEndpoint . '/' . $id;
        if (($content = $this->fetchData('GET', $endpoint)) === null) {
            return false;
        }

        $auth = new AuthResponse();

        $data = json_decode($content, true);

        $this->serializer->deserialize(json_encode($data['response']['auth']), AuthResponse::class, 'json', [
            AbstractNormalizer::OBJECT_TO_POPULATE => $auth,
        ]);

        if (!is_null($auth->getAuthenticated())) {
            return true;
        }

        return false;
    }

    public function setAuthEndpoint(string $authEndpoint): void
    {
        $this->authEndpoint = $authEndpoint;
    }
}