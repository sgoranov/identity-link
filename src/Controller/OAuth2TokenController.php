<?php
declare(strict_types=1);

namespace App\Controller;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class OAuth2TokenController extends AbstractController
{
    public function __construct(
        private readonly AuthorizationServer $server,
        private readonly HttpFoundationFactoryInterface $httpFoundationFactory,
        private readonly HttpMessageFactoryInterface $httpMessageFactory,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly LoggerInterface $logger,
    )
    {
    }

    #[Route('/oauth2/token', name: 'oauth2_token', methods: 'POST')]
    public function __invoke(Request $request): Response
    {
        $psrRequest = $this->httpMessageFactory->createRequest($request);
        $psrResponse = $this->responseFactory->createResponse();

        try {

            $response = $this->server->respondToAccessTokenRequest($psrRequest, $psrResponse);

            $this->logger->info('OAuth2 token request successful.');
        } catch (OAuthServerException $exception) {

            $this->logger->warning('OAuth2 token request failed.', [
                'error_type' => $exception->getErrorType(),
                'http_status' => $exception->getHttpStatusCode(),
                'grant_type' => $request->request->get('grant_type'),
            ]);

            $response = $exception->generateHttpResponse($psrResponse);
        }

        return $this->httpFoundationFactory->createResponse($response);
    }
}
