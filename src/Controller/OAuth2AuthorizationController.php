<?php
declare(strict_types=1);

namespace App\Controller;

use App\Api\Contract\ClientConnectorInterface;
use App\Api\Contract\UserConnectorInterface;
use App\Entity\AuthorizationSession;
use App\Form\Type\ConsentType;
use App\LeagueOAuth2\Entity\UserEntity;
use App\Security\AuthorizationSessionResolver;
use App\Security\Exception\UserNotFoundException;
use App\Security\AuthorizationFlowFactory;
use App\Security\AuthorizationStateEnum;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OAuth2AuthorizationController extends AbstractController
{
    public function __construct(
        private readonly AuthorizationServer $server,
        private readonly HttpFoundationFactoryInterface $httpFoundationFactory,
        private readonly HttpMessageFactoryInterface $httpMessageFactory,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly UserConnectorInterface $userConnector,
        private readonly EntityManagerInterface $entityManager,
        private readonly FormFactoryInterface $formFactory,
        private readonly ClientConnectorInterface $clientConnector,
        private readonly AuthorizationSessionResolver $authorizationSessionResolver,
        private readonly LoggerInterface $logger,
        private readonly AuthorizationFlowFactory $authorizationFlowFactory,
    )
    {
    }

    #[Route('/oauth2/auth', name: 'oauth2_auth', methods: ['GET'])]
    public function authorize(Request $request): Response | RedirectResponse
    {
        $psrRequest = $this->httpMessageFactory->createRequest($request);
        $psrResponse = $this->responseFactory->createResponse();

        try {
            $validated = $this->server->validateAuthorizationRequest($psrRequest);

            // enforce PKCE policy
            if ($validated->getCodeChallengeMethod() === 'plain') {
                throw OAuthServerException::invalidRequest('code_challenge_method', 'Plain code challenge method is not allowed for this client');
            }

            // Every authorization request starts a new login: persist a short-lived snapshot
            // and redirect to the first step of the authorization flow
            $authorizationSession = new AuthorizationSession();
            $authorizationSession->setQueryParams($request->query->all());
            $authorizationSession->setClientId($validated->getClient()->getIdentifier());
            $authorizationSession->setScopes($validated->getScopes());;

            $this->entityManager->persist($authorizationSession);
            $this->entityManager->flush();

            $firstState = $this->authorizationFlowFactory->create($authorizationSession)->resolveNextState();
            return $this->redirectToRoute($firstState->value, ['id' => $authorizationSession->getId()]);

        } catch (OAuthServerException $e) {
            $this->logger->warning('OAuth2 authorization request failed.', [
                'error_type' => $e->getErrorType(),
                'http_status' => $e->getHttpStatusCode(),
            ]);

            return $this->httpFoundationFactory->createResponse($e->generateHttpResponse($psrResponse));
        }
    }

    #[Route('/oauth2/auth/{id}', name: 'oauth2_auth_complete', methods: ['GET', 'POST'])]
    public function complete(string $id, Request $request): Response
    {
        // The user is the one identified by the login flow of this authorization request
        $authorizationSession = $this->authorizationSessionResolver->resolve($id);
        $this->authorizationFlowFactory->create($authorizationSession)->assertStateAllowed(AuthorizationStateEnum::AUTHORIZATION_CODE);

        $psrRequest =  $this->httpMessageFactory->createRequest(
            $request->duplicate($authorizationSession->getQueryParams(), null, ['_route' => 'oauth2_auth_complete'])
        );
        $psrResponse = $this->responseFactory->createResponse();

        try {
            $validated = $this->server->validateAuthorizationRequest($psrRequest);

            // enforce PKCE policy
            if ($validated->getCodeChallengeMethod() === 'plain') {
                throw OAuthServerException::invalidRequest('code_challenge_method', 'Plain code challenge method is not allowed for this client');
            }

            // Verify the user again just before completing the authorization.
            // This ensures the user still exists and is valid (e.g., the account may have been deleted or disabled)
            $currentUser = $this->userConnector->getUserById($authorizationSession->getUserIdentifier());
            if ($currentUser === null) {
                throw new UserNotFoundException($authorizationSession, $authorizationSession->getUserIdentifier());
            }

            $userEntity = new UserEntity();
            $userEntity->setIdentifier($currentUser->getId());

            $validated->setUser($userEntity);
            $validated->setAuthorizationApproved(true);

            $final = $this->server->completeAuthorizationRequest($validated, $psrResponse);

            $this->logger->info('OAuth2 authorization code issued.');

            $authorizationSession->setAuthorizationState(AuthorizationStateEnum::AUTHORIZATION_CODE);
            $authorizationSession->consume();
            $this->entityManager->persist($authorizationSession);
            $this->entityManager->flush();

            return $this->httpFoundationFactory->createResponse($final);
        } catch (OAuthServerException $e) {
            $this->logger->warning('OAuth2 authorization request failed.', [
                'error_type' => $e->getErrorType(),
                'http_status' => $e->getHttpStatusCode(),
            ]);

            return $this->httpFoundationFactory->createResponse($e->generateHttpResponse($psrResponse));
        }
    }

    #[Route('/oauth2/auth/consent-screen/{id}', name: 'oauth2_auth_consent_screen', methods: ['GET', 'POST'])]
    public function consentScreen(string $id, Request $request): Response
    {
        $authorizationSession = $this->authorizationSessionResolver->resolve($id);
        $authorizationFlow = $this->authorizationFlowFactory->create($authorizationSession);
        $authorizationFlow->assertStateAllowed(AuthorizationStateEnum::CONSENT);

        $form = $this->formFactory->create(ConsentType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if ($form->get('deny')->isClicked()) {

                // Denying ends the session without completing the flow: its state stays at the
                // last step before consent, so consuming it is what prevents any further use
                $authorizationSession->consume();
                $this->entityManager->persist($authorizationSession);
                $this->entityManager->flush();

                $params = $authorizationSession->getQueryParams();
                $redirectUri = $params['redirect_uri'] ?? null;
                $state = $params['state'] ?? null;

                $query = http_build_query([
                    'error' => 'access_denied',
                    'error_description' => 'User denied consent',
                    'state' => $state,
                ]);

                $redirectUrl = $redirectUri . (str_contains($redirectUri, '?') ? '&' : '?') . $query;

                return new RedirectResponse($redirectUrl);
            }

            $authorizationSession->setAuthorizationState(AuthorizationStateEnum::CONSENT);
            $this->entityManager->persist($authorizationSession);
            $this->entityManager->flush();

            $nextState = $authorizationFlow->resolveNextState();
            return $this->redirectToRoute($nextState->value, ['id' => $id]);
        }

        return $this->render('authorization/consent_screen.html.twig', [
            'form' => $form->createView(),
            'client' => $this->clientConnector->getClientById($authorizationSession->getClientId()),
            'scopes' => $authorizationSession->getScopes(),
        ]);
    }
}
