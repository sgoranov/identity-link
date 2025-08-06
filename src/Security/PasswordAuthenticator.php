<?php
declare(strict_types=1);

namespace App\Security;

use App\Api\Contract\ClientConnectorInterface;
use App\Api\Contract\TwoFaConnectorInterface;
use App\Api\Contract\UserConnectorInterface;
use App\Api\IdentityLink\Response\DbUserResponse;
use App\Form\Type\LoginType;
use App\LeagueOAuth2\Entity\ClientEntity;
use App\LeagueOAuth2\Entity\GrantTypeEntity;
use App\Security\Exception\TwoFaAuthRequiredException;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

class PasswordAuthenticator extends AbstractAuthenticator
    implements AuthenticationEntryPointInterface, InteractiveAuthenticatorInterface
{
    private string $twoFaIndexEndpoint;

    public function __construct(
        private readonly RouterInterface $router,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly FormFactoryInterface $formFactory,
        private readonly UserConnectorInterface $userConnector,
        private readonly ClientConnectorInterface $clientConnector,
        private readonly TwoFaConnectorInterface $twoFaConnector,
        private readonly RequestStack $requestStack,
        private readonly RateLimiterFactory $loginIpLimiter,
        private readonly RateLimiterFactory $loginUsernameLimiter,
        private readonly ParameterBagInterface $parameterBag,
    )
    {
    }

    public function setTwoFaIndexEndpoint(string $twoFaIndexEndpoint): void
    {
        $this->twoFaIndexEndpoint = $twoFaIndexEndpoint;
    }

    public function authenticate(Request $request): Passport
    {
        $session = $this->requestStack->getCurrentRequest()->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        $routeName = $request->attributes->get('_route');

        if ($routeName === 'security_login') {

            $form = $this->formFactory->create(LoginType::class);
            $form->handleRequest($request);

            if (!$form->isSubmitted() || !$form->isValid()) {
                throw new AuthenticationException('Invalid username or password');
            }

            $data = $form->getData();

            $ipLimiter = $this->loginIpLimiter->create($request->getClientIp());
            $usernameLimiter = $this->loginUsernameLimiter->create($data['user_id']);

            $ipLimit = $ipLimiter->consume();
            $usernameLimit = $usernameLimiter->consume();

            if (!$ipLimit->isAccepted() || !$usernameLimit->isAccepted()) {
                $retryAfter = max($ipLimit->getRetryAfter()->getTimestamp(), $usernameLimit->getRetryAfter()->getTimestamp()) - time();

                $minutes = floor($retryAfter / 60);
                $seconds = $retryAfter % 60;

                throw new AuthenticationException(sprintf(
                    'Too many login attempts. Please try again in %d minute(s) and %d second(s).', $minutes, $seconds));
            }

            $user = $this->userConnector->getUserByUserCredentials($data['user_id'], $data['password'],
                GrantTypeEntity::AUTHORIZATION_CODE, $this->loadClientFromSession($request));
            if ($user === null) {
                throw new AuthenticationException('Invalid username or password');
            }

            // user logged in successfully and 2FA is disabled
            // we can proceed creating the passport
            if ($this->twoFaConnector->isTwoFaEnabled() === false) {
                return new SelfValidatingPassport(new UserBadge($user->getId()));
            }

            // 2FA is required
            $twoFaId = $this->twoFaConnector->initiateAuthenticationRequest($user->getId());

            if ($twoFaId !== null) {

                $session->set('auth_user_id', $user->getId());
                $session->set('auth_2fa_id', $twoFaId);

                throw new TwoFaAuthRequiredException($twoFaId);
            }
        }

        // Verify that 2FA passes
        $twoFaId = $session->get('auth_2fa_id');
        if ($routeName === 'security_2fa' && $twoFaId !== null &&
            $this->twoFaConnector->validateAuthenticationRequest($twoFaId) &&
            ($user = $this->loadUserFromSession($request)) !== null) {

            return new SelfValidatingPassport(new UserBadge($user->getId()));
        }

        throw new AuthenticationException('Please login');
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $params = $request->getSession()->get('auth_request_params', []);

        return new RedirectResponse($this->router->generate('oauth2_auth', $params));
    }

    public function supports(Request $request): ?bool
    {
        if ($this->parameterBag->get('authenticator_type') !== 'PasswordAuthenticator') {
            return false;
        }

        $url = $request->getBaseUrl() . $request->getPathInfo();

        if ($request->isMethod('POST') && ($this->router->generate('security_login') === $url)) {
            return true;
        }

        if ($this->router->generate('security_2fa') === $url) {
            return true;
        }

        return false;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // redirect the user to 2FA verification page
        if ($exception instanceof TwoFaAuthRequiredException) {
            return new RedirectResponse(
                str_replace('{id}', $exception->getAuthId(), $this->twoFaIndexEndpoint));
        }

        if ($request->hasSession()) {
            $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);
        }

        return new RedirectResponse($this->router->generate('security_login'));
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        $request->getSession()->set('auth_request_params', $request->query->all());

        return new RedirectResponse($this->urlGenerator->generate('security_login'));
    }

    public function isInteractive(): bool
    {
        return true;
    }

    private function loadUserFromSession(Request $request): ?DbUserResponse
    {
        $id = $request->getSession()->get('auth_user_id', false);
        if ($id === false) {
            return null;
        }

        $user = $this->userConnector->getUserById($id);
        if (!$user) {
            throw new AuthenticationException('Invalid user id');
        }

        return $user;
    }

    private function loadClientFromSession(Request $request): ClientEntityInterface
    {
        $params = $request->getSession()->get('auth_request_params', []);
        if (!isset($params['client_id'])) {
            throw new AuthenticationException('Invalid client id');
        }

        $client = $this->clientConnector->getClientById($params['client_id']);
        if (!$client) {
            throw new AuthenticationException('Invalid client id');
        }

        $entity = new ClientEntity();
        $entity->setIdentifier($client->getName());
        $entity->setRedirectUri($client->getRedirectUri());
        $entity->setPublic($client->isPublic());
        $entity->setScopes($client->getScopes());
        $entity->setGrantTypes($client->getGrantTypes());

        return $entity;
    }
}