<?php
declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OidcDiscoveryController extends AbstractController
{
    #[Route('/.well-known/openid-configuration', name: 'oidc_discovery', methods: ['GET'])]
    public function discovery(UrlGeneratorInterface $urlGenerator): JsonResponse
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];

        return new JsonResponse([
            'issuer' => $protocol . $host,
            'authorization_endpoint' => $urlGenerator->generate('oauth2_auth', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'token_endpoint' => $urlGenerator->generate('oauth2_token', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'userinfo_endpoint' => $urlGenerator->generate('oidc_user_info', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'jwks_uri' => $urlGenerator->generate('oidc_jwks', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'response_types_supported' => [
                'code',
            ],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'code_challenge_methods_supported' => ['S256'],
        ]);
    }
}