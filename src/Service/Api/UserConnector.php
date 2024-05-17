<?php
declare(strict_types=1);

namespace App\Service\Api;

use App\Model\OAuth2\UserModel;
use App\Service\JwtTokenGenerator;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

class UserConnector implements UserConnectorInterface
{
    private string $userAuthEndpoint;
    private string $userFetchEndpoint;

    public function __construct(
        private readonly JwtTokenGenerator $jwtTokenGenerator,
        private readonly LoggerInterface $logger,
        private readonly SerializerInterface $serializer,
    )
    {
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

        $user = new UserModel();

        $data = json_decode($content, true);

        $this->serializer->deserialize(json_encode($data['response']['user']), UserModel::class, 'json', [
            AbstractNormalizer::OBJECT_TO_POPULATE => $user,
        ]);

        return $user;
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

        $user = new UserModel();

        $data = json_decode($content, true);

        $this->serializer->deserialize(json_encode($data['response']['user']), UserModel::class, 'json', [
            AbstractNormalizer::OBJECT_TO_POPULATE => $user,
        ]);

        return $user;
    }

    private function fetchData(string $method, string $endpoint, array $options = []): ?string
    {
        $token = $this->jwtTokenGenerator
            ->setGroups(['administrator'])
            ->setSubject('internal')
            ->setAudience('php-identity-link')
            ->setIssuer('php-identity-link')
            ->loadTokenFromCache();

        $options['headers']['Authorization'] = 'Bearer ' . $token;

        $client = HttpClient::create();

        try {

            $result = $client->request($method, $endpoint, $options);

            return $result->getContent();

        } catch (ClientExceptionInterface $e) {

            $response = $e->getResponse();
            if ($response->getStatusCode() !== Response::HTTP_BAD_REQUEST) {
                $this->logger->error('An error occurred: ' . $e->getMessage());
            }

        } catch (ExceptionInterface $e) {

            $this->logger->error('An error occurred: ' . $e->getMessage());
        }

        return null;
    }
}