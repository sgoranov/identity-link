<?php
declare(strict_types=1);

namespace App\Tests\Unit\Api\Shared;

use App\Api\Shared\AbstractConnector;
use App\Service\JwtTokenGenerator;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class AbstractConnectorTest extends TestCase
{
    public function testForwardsTheRequestIdOfTheMainRequest(): void
    {
        $mainRequest = new Request();
        $mainRequest->headers->set('X-Request-Id', '0123456789abcdef0123456789abcdef');
        $requestStack = new RequestStack();
        $requestStack->push($mainRequest);
        $requestStack->push(new Request());

        $headers = $this->sendRequest($requestStack);

        $this->assertSame(['X-Request-Id: 0123456789abcdef0123456789abcdef'], $headers['x-request-id']);
        $this->assertSame(['Authorization: Bearer internal-token'], $headers['authorization']);
    }

    public function testSendsNoRequestIdWhenTheRequestHasNone(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $headers = $this->sendRequest($requestStack);

        $this->assertArrayNotHasKey('x-request-id', $headers);
    }

    public function testSendsNoRequestIdOutsideARequest(): void
    {
        $headers = $this->sendRequest(new RequestStack());

        $this->assertArrayNotHasKey('x-request-id', $headers);
    }

    /**
     * @return array<string, list<string>> the headers sent, keyed by lowercase name
     */
    private function sendRequest(RequestStack $requestStack): array
    {
        $response = new MockResponse('{}');

        $jwtTokenGenerator = $this->createStub(JwtTokenGenerator::class);
        $jwtTokenGenerator->method('setSubject')->willReturnSelf();
        $jwtTokenGenerator->method('loadTokenFromCache')->willReturn('internal-token');

        $connector = new class($jwtTokenGenerator, new NullLogger(), new MockHttpClient($response), $requestStack)
            extends AbstractConnector {
            public function get(string $endpoint): ?string
            {
                return $this->fetchData('GET', $endpoint);
            }
        };

        $this->assertSame('{}', $connector->get('http://identity-link-db-users:9001/api/v1/user/1'));

        return $response->getRequestOptions()['normalized_headers'];
    }
}
