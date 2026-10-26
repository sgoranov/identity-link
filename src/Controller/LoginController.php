<?php
declare(strict_types=1);

namespace App\Controller;

use App\Api\Contract\TwoFaConnectorInterface;
use App\Api\Contract\UserConnectorInterface;
use App\Form\Type\LoginType;
use App\LeagueOAuth2\Entity\GrantTypeEntity;
use App\LeagueOAuth2\Repository\ClientRepository;
use App\Security\Attribute\MapAuthorizationFlow;
use App\Security\AuthorizationFlow;
use App\Security\AuthorizationStateEnum;
use App\Security\Exception\RateLimitException;
use App\Security\Exception\TwoFactorAuthenticationFailedException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RateLimiter\PeekableRequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

class LoginController extends AbstractController
{
    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly ClientRepository $clientRepository,
        private readonly FormFactoryInterface $formFactory,
        private readonly PeekableRequestRateLimiterInterface $loginRateLimiter,
        private readonly UserConnectorInterface $userConnector,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TwoFaConnectorInterface $twoFaConnector,
    )
    {
    }

    #[Route('/login/password/{id}',
        name: 'login_with_password',
        requirements: ['id' => Requirement::UUID],
        methods: ['GET', 'POST'])
    ]
    public function login(
        #[MapAuthorizationFlow(AuthorizationStateEnum::PASSWORD)] AuthorizationFlow $authorizationFlow,
        Request $request,
    ): Response
    {
        $authorizationSession = $authorizationFlow->getAuthorizationSession();

        $client = $this->clientRepository->getClientEntity($authorizationSession->getClientId());
        $form = $this->formFactory->create(LoginType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Only failed logins are throttled, like Symfony's login throttling: the limit is checked
            // without consuming, a failed login consumes and a successful one resets the username limit
            $request->attributes->set(SecurityRequestAttributes::LAST_USERNAME, (string) $data['user_id']);

            try {
                $this->assertLoginAllowed($request);
            } catch (AuthenticationException $exception) {
                $this->addFlash('error', [
                    'key' => $exception->getMessageKey(),
                    'params' => $exception->getMessageData()
                ]);

                return $this->redirectToRoute('login_with_password', ['id' => $authorizationSession->getId()]);
            }

            $user = $this->userConnector->getUserByUserCredentials(
                $data['user_id'],
                $data['password'],
                GrantTypeEntity::AUTHORIZATION_CODE,
                $client
            );

            if ($user === null) {
                $this->loginRateLimiter->consume($request);

                $this->addFlash('error', [
                    'key' => 'login.invalid_credentials',
                    'params' => []
                ]);

                return $this->redirectToRoute('login_with_password', ['id' => $authorizationSession->getId()]);
            }

            // Resets the limit of the username only, not of the IP, so logging in to one account
            // can't be used to keep guessing the passwords of others
            $this->loginRateLimiter->reset($request);

            $authorizationSession->setAuthorizationState(AuthorizationStateEnum::PASSWORD);
            $authorizationSession->setUserIdentifier($user->getId());
            $this->entityManager->persist($authorizationSession);;
            $this->entityManager->flush();

            $nextState = $authorizationFlow->resolveNextState();
            return $this->redirectToRoute($nextState->value, ['id' => $authorizationSession->getId()]);
        }

        return $this->render('login/login.html.twig', [
            'form' => $this->createForm(LoginType::class),
            'scopes' => $authorizationSession->getScopes(),
            'client_name' => $client->getName(),
            'resetPasswordUrl' => str_replace('{id}', $authorizationSession->getId(), $this->parameterBag->get('reset_password_url')),
        ]);
    }

    #[Route('/login/2fa/initiate/{id}',
        name: 'login_2fa_initiate',
        requirements: ['id' => Requirement::UUID],
        methods: ['GET'])
    ]
    public function twoFaInitiate(
        #[MapAuthorizationFlow(AuthorizationStateEnum::TWO_FACTOR_INITIATE)] AuthorizationFlow $authorizationFlow,
    ): RedirectResponse
    {
        $authorizationSession = $authorizationFlow->getAuthorizationSession();

        $redirectUrl = $this->urlGenerator->generate('login_2fa_complete', ['id' => $authorizationSession->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $twoFaId = $this->twoFaConnector->initiateAuthenticationRequest($authorizationSession->getUserIdentifier(), $redirectUrl);
        if (null === $twoFaId) {
            throw new \RuntimeException('Unable to initiate two-factor authentication.');
        }

        $authorizationSession->setAuthorizationState(AuthorizationStateEnum::TWO_FACTOR_INITIATE);
        $authorizationSession->setTwoFaRequestId($twoFaId);
        $this->entityManager->persist($authorizationSession);;
        $this->entityManager->flush();

        return new RedirectResponse(
            str_replace('{id}', $twoFaId, $this->parameterBag->get('two_fa_index_endpoint'))
        );
    }

    #[Route('/login/2fa/complete/{id}',
        name: 'login_2fa_complete',
        requirements: ['id' => Requirement::UUID],
        methods: ['GET'])
    ]
    public function twoFaComplete(
        #[MapAuthorizationFlow(AuthorizationStateEnum::TWO_FACTOR_COMPLETE)] AuthorizationFlow $authorizationFlow,
    ): RedirectResponse
    {
        $authorizationSession = $authorizationFlow->getAuthorizationSession();

        // Only the 2FA request initiated for this session counts, so its id is taken from the session
        // and not from the redirect back from the 2FA service, which could carry the id of another request
        $twoFaId = $authorizationSession->getTwoFaRequestId();
        $twoFaResponse = $twoFaId === null ? null : $this->twoFaConnector->validateAuthenticationRequest($twoFaId);
        if (null === $twoFaResponse || $twoFaResponse->getUserId() !== $authorizationSession->getUserIdentifier()) {
            throw new TwoFactorAuthenticationFailedException($authorizationSession);
        }

        $authorizationSession->setAuthorizationState(AuthorizationStateEnum::TWO_FACTOR_COMPLETE);
        $this->entityManager->persist($authorizationSession);;
        $this->entityManager->flush();

        $nextState = $authorizationFlow->resolveNextState();
        return $this->redirectToRoute($nextState->value, ['id' => $authorizationSession->getId()]);
    }

    #[Route('/login/complete/{id}',
        name: 'login_complete',
        requirements: ['id' => Requirement::UUID],
        methods: ['GET'])
    ]
    public function complete(
        #[MapAuthorizationFlow(AuthorizationStateEnum::COMPLETED)] AuthorizationFlow $authorizationFlow,
    ): RedirectResponse
    {
        $authorizationSession = $authorizationFlow->getAuthorizationSession();

        $authorizationSession->setAuthorizationState(AuthorizationStateEnum::COMPLETED);
        $this->entityManager->persist($authorizationSession);
        $this->entityManager->flush();

        $nextState = $authorizationFlow->resolveNextState();
        return $this->redirectToRoute($nextState->value, ['id' => $authorizationSession->getId()]);
    }

    /**
     * @throws RateLimitException when the client IP or the username has too many failed logins
     */
    private function assertLoginAllowed(Request $request): void
    {
        $limit = $this->loginRateLimiter->peek($request);

        // peek() consumes no tokens, so it is accepted even when no tokens are left
        if (!$limit->isAccepted() || $limit->getRemainingTokens() === 0) {
            $retryAfter = max(0, $limit->getRetryAfter()->getTimestamp() - time());

            $minutes = (int) floor($retryAfter / 60);
            $seconds = $retryAfter % 60;

            throw new RateLimitException($minutes, $seconds);
        }
    }
}
