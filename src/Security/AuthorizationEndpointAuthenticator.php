<?php
declare(strict_types=1);

namespace App\Security;

use App\Entity\AuthRequest;
use App\Repository\AuthRequestRepository;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
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
            $this->urlGenerator->generate('login_dispatch', ['id' => $request->get('id')])
        );
    }

    public function authenticate(Request $request): Passport
    {
        $id = $request->get('id');
        $authRequest = $this->authRequestRepository->findActive($id);
        if (null === $authRequest) {
            throw new AuthenticationException('Invalid authentication request.');
        }

        if ($authRequest->getLoginState() !== LoginStateEnum::COMPLETED) {
            throw new AuthenticationException('Login flow not completed.');
        }

        return new SelfValidatingPassport(new UserBadge($authRequest->getUserIdentifier()));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function supports(Request $request): ?bool
    {
        return $request->get('_route') === 'oauth2_auth_complete' &&
            $this->resolveAuthRequest($request) !== null;
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

    private function resolveAuthRequest(Request $request): ?AuthRequest
    {
        $id = $request->attributes->get('id') ?? $request->query->get('id');
        if (!is_string($id) || $id === '') {
            throw new BadRequestException('Missing authentication request');
        }

        $authRequest = $this->authRequestRepository->findActive($id);
        if (null === $authRequest) {
            throw new BadRequestException('Invalid authentication request');
        }

        return $authRequest;
    }
}