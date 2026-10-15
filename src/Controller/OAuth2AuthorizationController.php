<?php
declare(strict_types=1);

namespace App\Controller;

use App\Api\Contract\ClientConnectorInterface;
use App\Api\Contract\UserConnectorInterface;
use App\Entity\AuthorizationSession;
use App\Form\Type\ConsentType;
use App\LeagueOAuth2\Entity\UserEntity;
use App\Security\Attribute\MapAuthorizationFlow;
use App\Security\AuthorizationFlow;
use App\Security\AuthorizationFlowFactory;
use App\Security\AuthorizationSessionBinding;
use App\Security\AuthorizationStateEnum;
use App\Security\Exception\UserNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
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
use Symfony\Component\Routing\Requirement\Requirement;

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
        private readonly LoggerInterface $logger,
        private readonly AuthorizationFlowFactory $authorizationFlowFactory,
        private readonly AuthorizationSessionBinding $authorizationSessionBinding,
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

            $firstState = $this->authorizationFlowFactory->create($authorizationSession)->resolveNextState();
            $response = $this->redirectToRoute($firstState->value, ['id' => $authorizationSession->getId()]);

            // The binding cookie is named after the id, which is assigned on persist
            $this->authorizationSessionBinding->bind($authorizationSession, $response);
            $this->entityManager->flush();

            return $response;

        } catch (OAuthServerException $e) {
            $this->logger->warning('OAuth2 authorization request failed.', [
                'error_type' => $e->getErrorType(),
                'http_status' => $e->getHttpStatusCode(),
            ]);

            return $this->httpFoundationFactory->createResponse($e->generateHttpResponse($psrResponse));
        }
    }

    #[Route('/oauth2/auth/{id}',
        name: 'oauth2_auth_complete',
        requirements: ['id' => Requirement::UUID],
        methods: ['GET', 'POST'])
    ]
    public function complete(
        #[MapAuthorizationFlow(AuthorizationStateEnum::AUTHORIZATION_CODE)] AuthorizationFlow $authorizationFlow,
        Request $request,
    ): Response
    {
        // The user is the one identified by the login flow of this authorization request
        $authorizationSession = $authorizationFlow->getAuthorizationSession();
        $psrResponse = $this->responseFactory->createResponse();

        try {
            $validated = $this->validateStoredAuthorizationRequest($authorizationSession, $request);

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

            $response = $this->httpFoundationFactory->createResponse($final);
            $this->authorizationSessionBinding->unbind($authorizationSession, $response);

            return $response;
        } catch (OAuthServerException $e) {
            $this->logger->warning('OAuth2 authorization request failed.', [
                'error_type' => $e->getErrorType(),
                'http_status' => $e->getHttpStatusCode(),
            ]);

            return $this->httpFoundationFactory->createResponse($e->generateHttpResponse($psrResponse));
        }
    }

    #[Route('/oauth2/auth/consent-screen/{id}',
        name: 'oauth2_auth_consent_screen',
        requirements: ['id' => Requirement::UUID],
        methods: ['GET', 'POST'])
    ]
    public function consentScreen(
        #[MapAuthorizationFlow(AuthorizationStateEnum::CONSENT)] AuthorizationFlow $authorizationFlow,
        Request $request,
    ): Response
    {
        $authorizationSession = $authorizationFlow->getAuthorizationSession();

        $form = $this->formFactory->create(ConsentType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if ($form->get('deny')->isClicked()) {

                // Denying ends the session without completing the flow: its state stays at the
                // last step before consent, so consuming it is what prevents any further use
                $authorizationSession->consume();
                $this->entityManager->persist($authorizationSession);
                $this->entityManager->flush();

                $response = $this->denyAuthorizationRequest($authorizationSession, $request);
                $this->authorizationSessionBinding->unbind($authorizationSession, $response);

                return $response;
            }

            $authorizationSession->setAuthorizationState(AuthorizationStateEnum::CONSENT);
            $this->entityManager->persist($authorizationSession);
            $this->entityManager->flush();

            $nextState = $authorizationFlow->resolveNextState();
            return $this->redirectToRoute($nextState->value, ['id' => $authorizationSession->getId()]);
        }

        return $this->render('authorization/consent_screen.html.twig', [
            'form' => $form->createView(),
            'client' => $this->clientConnector->getClientById($authorizationSession->getClientId()),
            'scopes' => $authorizationSession->getScopes(),
        ]);
    }

    /**
     * Returns the "access_denied" error to the client. League resolves the redirect URI (the one
     * of the request, or the one registered for the client when the request has none) and the state.
     */
    private function denyAuthorizationRequest(AuthorizationSession $authorizationSession, Request $request): Response
    {
        $psrResponse = $this->responseFactory->createResponse();

        try {
            $validated = $this->validateStoredAuthorizationRequest($authorizationSession, $request);

            // League requires a user, also for a denied request
            $userEntity = new UserEntity();
            $userEntity->setIdentifier($authorizationSession->getUserIdentifier());

            $validated->setUser($userEntity);
            $validated->setAuthorizationApproved(false);

            // League doesn't return for a denied request: it throws "access_denied" with the redirect
            // URI and state, and the exception builds the redirect to the client
            $this->server->completeAuthorizationRequest($validated, $psrResponse);
        } catch (OAuthServerException $e) {
            $this->logger->info('OAuth2 authorization request denied.', [
                'error_type' => $e->getErrorType(),
                'http_status' => $e->getHttpStatusCode(),
            ]);

            return $this->httpFoundationFactory->createResponse($e->generateHttpResponse($psrResponse));
        }

        throw new \LogicException('League completed an authorization request that was not approved.');
    }

    /**
     * Rebuilds the authorization request from the query params stored in the session, as league
     * needs it to complete the request. Validating it again also rejects a client that changed
     * since the request started, e.g. one that was removed.
     *
     * @throws OAuthServerException
     */
    private function validateStoredAuthorizationRequest(AuthorizationSession $authorizationSession, Request $request): AuthorizationRequestInterface
    {
        $psrRequest = $this->httpMessageFactory->createRequest(
            $request->duplicate($authorizationSession->getQueryParams())
        );

        $validated = $this->server->validateAuthorizationRequest($psrRequest);

        // enforce PKCE policy
        if ($validated->getCodeChallengeMethod() === 'plain') {
            throw OAuthServerException::invalidRequest('code_challenge_method', 'Plain code challenge method is not allowed for this client');
        }

        return $validated;
    }
}
