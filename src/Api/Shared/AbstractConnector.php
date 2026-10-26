<?php
declare(strict_types=1);

namespace App\Api\Shared;

use App\Service\JwtTokenGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class AbstractConnector
{
    public function __construct(
        private readonly JwtTokenGenerator $jwtTokenGenerator,
        private readonly LoggerInterface $logger,
        private readonly HttpClientInterface $httpClient,
        private readonly RequestStack $requestStack,
    )
    {
    }

    protected function fetchData(string $method, string $endpoint, array $options = []): ?string
    {
        $token = $this->jwtTokenGenerator
            ->setSubject('internal')
            ->loadTokenFromCache();

        $options['headers']['Authorization'] = 'Bearer ' . $token;

        // The services are called directly rather than through nginx, so pass on the ID nginx gave the incoming
        // request; their logs then share it. There is no request in console commands.
        $requestId = $this->requestStack->getMainRequest()?->headers->get('X-Request-Id');
        if (null !== $requestId) {
            $options['headers']['X-Request-Id'] = $requestId;
        }

        try {

            $result = $this->httpClient->request($method, $endpoint, $options);

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
