<?php
declare(strict_types=1);

namespace App\Security;

use App\Repository\AuthRequestRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class AuthorizationEndpointAuthenticator extends AbstractAuthenticator implements
    AuthenticationEntryPointInterface, InteractiveAuthenticatorInterface
{

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly AuthRequestRepository $authRequestRepository,
    )
    {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse(
            $this->urlGenerator->generate('login_with_password', ['id' => $request->get('id')])
        );
    }

    public function authenticate(Request $request): Passport
    {
        $authRequest = $this->authRequestRepository->findActive($request->get('id'));
        if ($authRequest === null || $authRequest->getLoginState() !== LoginStateEnum::COMPLETED) {
            throw new AuthenticationException('Invalid or uncompleted authentication request.');
        }

        return new SelfValidatingPassport(new UserBadge($authRequest->getUserIdentifier()));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function supports(Request $request): ?bool
    {
        return $request->get('_route') === 'oauth2_auth_complete';
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // Log or debug if needed
        // dump($exception->getMessage());
        // exit();

        // continue with normal request handling
        return null;
    }

    public function isInteractive(): bool
    {
        return true;
    }
}