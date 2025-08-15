<?php
declare(strict_types=1);

namespace App\Controller;

use App\Api\Contract\TwoFaConnectorInterface;
use App\Api\Contract\UserConnectorInterface;
use App\Entity\AuthRequest;
use App\Form\Type\LoginType;
use App\LeagueOAuth2\Entity\GrantTypeEntity;
use App\LeagueOAuth2\Repository\ClientRepository;
use App\Repository\AuthRequestRepository;
use App\Security\LoginDispatcherService;
use App\Security\LoginStateEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class LoginController extends AbstractController
{
    public function __construct(
        private readonly ParameterBagInterface   $parameterBag,
        private readonly AuthRequestRepository   $authRequestRepository,
        private readonly ClientRepository        $clientRepository,
        private readonly LoginDispatcherService  $dispatcherService,
        private readonly FormFactoryInterface    $formFactory,
        private readonly RateLimiterFactory      $loginIpLimiter,
        private readonly RateLimiterFactory      $loginUsernameLimiter,
        private readonly UserConnectorInterface  $userConnector,
        private readonly EntityManagerInterface  $entityManager,
        private readonly UrlGeneratorInterface   $urlGenerator,
        private readonly TwoFaConnectorInterface $twoFaConnector,
    )
    {
    }

    #[Route('/login/dispatch/{id}', name: 'login_dispatch', methods: ['GET'])]
    public function dispatch(string $id): RedirectResponse
    {
        $nextState = $this->dispatcherService->getNextState();

        if ($nextState === null) {
            throw new BadRequestException('Invalid authentication request');
        }

        return $this->redirectToRoute($nextState->routeName(), ['id' => $id]);
    }

    #[Route('/login/password/{id}', name: 'login_with_password', methods: ['GET', 'POST'])]
    public function login(string $id, Request $request): Response
    {
        $authRequest = $this->resolveAuthRequest($id);
        $this->isLoginStateAllowed('login_with_password');

        $client = $this->clientRepository->getClientEntity($authRequest->getClientId());
        $form = $this->formFactory->create(LoginType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                $this->checkLoginRateLimits($request->getClientIp(), $data['user_id']);
            } catch (AuthenticationException $exception) {
                $this->addFlash('error', [
                    'key' => $exception->getMessageKey(),
                    'params' => $exception->getMessageData()
                ]);

                return $this->redirectToRoute('login_with_password', ['id' => $id]);
            }

            $user = $this->userConnector->getUserByUserCredentials(
                $data['user_id'],
                $data['password'],
                GrantTypeEntity::AUTHORIZATION_CODE,
                $client
            );

            if ($user === null) {
                $this->addFlash('error', [
                    'key' => 'Invalid username or password',
                    'params' => []
                ]);

                return $this->redirectToRoute('login_with_password', ['id' => $id]);
            }

            $authRequest->setLoginState(LoginStateEnum::PASSWORD);
            $authRequest->setUserIdentifier($user->getId());
            $this->entityManager->persist($authRequest);;
            $this->entityManager->flush();

            return $this->redirectToRoute('login_dispatch', ['id' => $id]);
        }

        return $this->render('login/login.html.twig', [
            'form' => $this->createForm(LoginType::class),
            'scopes' => $authRequest->getScopes(),
            'client_name' => $client->getName(),
            'resetPasswordUrl' => $this->parameterBag->get('reset_password_url'),
        ]);
    }

    #[Route('/login/2fa/initiate/{id}', name: 'login_2fa_initiate', methods: ['GET'])]
    public function twoFaInitiate(string $id): RedirectResponse
    {
        $authRequest = $this->resolveAuthRequest($id);
        $this->isLoginStateAllowed('login_2fa_initiate');

        $redirectUrl = $this->urlGenerator->generate('login_2fa_complete', ['id' => $authRequest->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $twoFaId = $this->twoFaConnector->initiateAuthenticationRequest($authRequest->getUserIdentifier(), $redirectUrl);
        if (null === $twoFaId) {
            throw new \RuntimeException('Unable to initiate two-factor authentication.');
        }

        $authRequest->setLoginState(LoginStateEnum::TWO_FACTOR_INITIATE);
        $this->entityManager->persist($authRequest);;
        $this->entityManager->flush();

        return new RedirectResponse(
            str_replace('{id}', $twoFaId, $this->parameterBag->get('two_fa_index_endpoint'))
        );
    }

    #[Route('/login/2fa/complete/{id}', name: 'login_2fa_complete', methods: ['GET'])]
    public function twoFaComplete(string $id, Request $request): RedirectResponse
    {
        $authRequest = $this->resolveAuthRequest($id);
        $this->isLoginStateAllowed('login_2fa_complete');

        $twoFaId = $request->query->get('id');
        if (!is_string($twoFaId) || $twoFaId === '') {
            throw new \RuntimeException('Unable to get two-factor authentication id.');
        }

        $twoFaResponse = $this->twoFaConnector->validateAuthenticationRequest($twoFaId);
        if (null === $twoFaResponse || $twoFaResponse->getUserId() !== $authRequest->getUserIdentifier()) {
            throw new \RuntimeException('Invalid two-factor authentication response.');
        }

        $authRequest->setLoginState(LoginStateEnum::TWO_FACTOR_COMPLETE);
        $this->entityManager->persist($authRequest);;
        $this->entityManager->flush();

        return $this->redirectToRoute('login_dispatch', ['id' => $id]);
    }

    #[Route('/login/complete/{id}', name: 'login_complete', methods: ['GET'])]
    public function complete(string $id): RedirectResponse
    {
        $authRequest = $this->resolveAuthRequest($id);
        $this->isLoginStateAllowed('login_complete');

        $authRequest->setLoginState(LoginStateEnum::COMPLETED);
        $this->entityManager->persist($authRequest);
        $this->entityManager->flush();

        return $this->redirectToRoute('oauth2_auth_complete', ['id' => $id]);
    }

    private function isLoginStateAllowed(string $stateToValidate): void
    {
        if (false === $this->dispatcherService->isStateAllowed($stateToValidate)) {
            throw new BadRequestException('Invalid login state');
        }
    }

    private function resolveAuthRequest(string $id): AuthRequest
    {
        $authRequest = $this->authRequestRepository->findActive($id);
        if (null === $authRequest) {
            throw new BadRequestException('Invalid authentication request');
        }

        return $authRequest;
    }

    private function checkLoginRateLimits(?string $ip, string $username): void
    {
        $ipLimiter = $this->loginIpLimiter->create($ip ?? 'unknown');
        $usernameLimiter = $this->loginUsernameLimiter->create($username);

        $ipLimit = $ipLimiter->consume();
        $usernameLimit = $usernameLimiter->consume();

        if (!$ipLimit->isAccepted() || !$usernameLimit->isAccepted()) {
            $retryAfter = max(
                    $ipLimit->getRetryAfter()->getTimestamp(),
                    $usernameLimit->getRetryAfter()->getTimestamp()
                ) - time();

            $minutes = floor($retryAfter / 60);
            $seconds = $retryAfter % 60;

            $exception = new AuthenticationException(sprintf(
                'Too many login attempts. Please try again in %d minute(s) and %d second(s).',
                $minutes,
                $seconds
            ));

            throw new $exception;
        }
    }
}
