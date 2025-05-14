<?php
declare(strict_types=1);

namespace App\Controller;

use App\Api\Contract\UserConnectorInterface;
use App\LeagueOAuth2\Entity\UserEntity;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AuthorizationController extends AbstractController
{
    public function __construct(
        private readonly AuthorizationServer $server,
        private readonly Security $security,
        private readonly HttpFoundationFactoryInterface $httpFoundationFactory,
        private readonly HttpMessageFactoryInterface $httpMessageFactory,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly UserConnectorInterface $userConnector,
    )
    {
    }

    #[Route('/oauth2/auth', name: 'oauth2_auth', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {

        $serverRequest = $this->httpMessageFactory->createRequest($request);
        $serverResponse = $this->responseFactory->createResponse();

        try {
            $authRequest = $this->server->validateAuthorizationRequest($serverRequest);

            // TODO: make it configurable adding isPlainTextPkceAllowed on client level
            if ('plain' === $authRequest->getCodeChallengeMethod()) {
                throw OAuthServerException::invalidRequest('code_challenge_method', 'Plain code challenge method is not allowed for this client');
            }

            $user = $this->userConnector->getUserById($this->getUser()->getUserIdentifier());

            $model = new UserEntity();
            $model->setIdentifier($user->getId());

            $authRequest->setUser($model);
            $authRequest->setAuthorizationApproved(true);

            $response = $this->server->completeAuthorizationRequest($authRequest, $serverResponse);

        } catch (OAuthServerException $e) {
            $response = $e->generateHttpResponse($serverResponse);
        }

        // logout the user before proceed
        $this->security->logout(false);

        return $this->httpFoundationFactory->createResponse($response);
    }
}