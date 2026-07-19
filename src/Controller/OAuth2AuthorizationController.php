<?php
declare(strict_types=1);

namespace App\Controller;

use App\Api\Contract\ClientConnectorInterface;
use App\Api\Contract\UserConnectorInterface;
use App\Entity\AuthRequest;
use App\Form\Type\ConsentType;
use App\LeagueOAuth2\Entity\UserEntity;
use App\Security\AuthRequestResolver;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseFactoryInterface;
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
        private readonly AuthRequestResolver $authRequestResolver,
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

            // Not logged in: persist a short-lived snapshot and redirect to login with request_id
            $authRequest = new AuthRequest();
            $authRequest->setQueryParams($request->query->all());
            $authRequest->setClientId($validated->getClient()->getIdentifier());
            $authRequest->setScopes($validated->getScopes());;

            $this->entityManager->persist($authRequest);
            $this->entityManager->flush();

            return new RedirectResponse($this->generateUrl('oauth2_auth_complete', ['id' => $authRequest->getId()]));

        } catch (OAuthServerException $e) {
            return $this->httpFoundationFactory->createResponse($e->generateHttpResponse($psrResponse));
        }
    }

    #[Route('/oauth2/auth/{id}', name: 'oauth2_auth_complete', methods: ['GET', 'POST'])]
    public function complete(string $id, Request $request): Response
    {
        // Ensure the user is authenticated. This route should be protected by the firewall.
        if (!$this->getUser()) {
            throw $this->createAccessDeniedException('User is not authenticated');
        }

        $authRequest = $this->authRequestResolver->resolve($id);
        $client = $this->clientConnector->getClientById($authRequest->getClientId());

        // Redirect the user to the consent screen before completing the OAuth2 authorization request.
        if ($client->isConsentRequired() && !$authRequest->getConsentApproved()) {
            return $this->redirectToRoute('oauth2_auth_consent_screen', ['id' => $id]);
        }

        $psrRequest =  $this->httpMessageFactory->createRequest(
            $request->duplicate($authRequest->getQueryParams(), null, ['_route' => 'oauth2_auth_complete'])
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
            $currentUser = $this->userConnector->getUserById($this->getUser()->getUserIdentifier());
            $userEntity = new UserEntity();
            $userEntity->setIdentifier($currentUser->getId());

            $validated->setUser($userEntity);
            $validated->setAuthorizationApproved(true);

            $final = $this->server->completeAuthorizationRequest($validated, $psrResponse);

            $authRequest->consume();
            $this->entityManager->persist($authRequest);
            $this->entityManager->flush();

            return $this->httpFoundationFactory->createResponse($final);
        } catch (OAuthServerException $e) {
            return $this->httpFoundationFactory->createResponse($e->generateHttpResponse($psrResponse));
        }
    }

    #[Route('/oauth2/auth/consent-screen/{id}', name: 'oauth2_auth_consent_screen', methods: ['GET', 'POST'])]
    public function consentScreen(string $id, Request $request): Response
    {
        $authRequest = $this->authRequestResolver->resolve($id);

        $form = $this->formFactory->create(ConsentType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if ($form->get('deny')->isClicked()) {

                $authRequest->consume();
                $authRequest->setConsentApproved(false);
                $this->entityManager->persist($authRequest);
                $this->entityManager->flush();

                $params = $authRequest->getQueryParams();
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

            $authRequest->setConsentApproved(true);
            $this->entityManager->persist($authRequest);
            $this->entityManager->flush();

            return $this->redirectToRoute('oauth2_auth_complete', ['id' => $id]);
        }

        return $this->render('authorization/consent_screen.html.twig', [
            'form' => $form->createView(),
            'client' => $this->clientConnector->getClientById($authRequest->getClientId()),
            'scopes' => $authRequest->getScopes(),
        ]);
    }
}