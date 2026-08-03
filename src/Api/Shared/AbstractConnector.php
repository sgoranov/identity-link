<?php
declare(strict_types=1);

namespace App\Api\Shared;

use App\Service\JwtTokenGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

abstract class AbstractConnector
{
    public function __construct(
        private readonly JwtTokenGenerator $jwtTokenGenerator,
        private readonly LoggerInterface $logger,
    )
    {
    }

    protected function fetchData(string $method, string $endpoint, array $options = []): ?string
    {
        $token = $this->jwtTokenGenerator
            ->setGroups(['administrator'])
            ->setSubject('internal')
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
