<?php
declare(strict_types=1);

namespace App\Api\IdentityLink\Connector;

use App\Api\Contract\TwoFaConnectorInterface;
use App\Api\IdentityLink\Response\AuthResponse;
use App\Api\Shared\AbstractConnector;
use App\Service\JwtTokenGenerator;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class TwoFaConnector extends AbstractConnector implements TwoFaConnectorInterface
{
    const CACHE_KEY = 'twofa_enabled';
    private string $authEndpoint;
    private string $pingEndpoint;

    public function __construct(
        private readonly JwtTokenGenerator $jwtTokenGenerator,
        private readonly LoggerInterface $logger,
        private readonly SerializerInterface $serializer,
        private readonly CacheItemPoolInterface $cache,
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

    public function isTwoFaEnabled(): bool
    {
        // In test environment, always disable 2FA to allow unit tests to run without external dependencies
        if ($_ENV['APP_ENV'] === 'test' || $_SERVER['APP_ENV'] === 'test') {
            return false;
        }

        $item = $this->cache->getItem(self::CACHE_KEY);
        if ($item->isHit()) {
            return $item->get();
        }

        $result = false;
        if ($this->fetchData('GET', $this->pingEndpoint) !== null) {
            $result = true;
        }

        $item->set($result);
        $item->expiresAfter(3600); // 1 hour
        $this->cache->save($item);

        return $result;
    }

    public function setPingEndpoint(string $pingEndpoint): void
    {
        $this->pingEndpoint = $pingEndpoint;
    }

    public function setAuthEndpoint(string $authEndpoint): void
    {
        $this->authEndpoint = $authEndpoint;
    }
}