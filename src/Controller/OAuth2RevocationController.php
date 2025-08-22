<?php
declare(strict_types=1);

namespace App\Controller;

use App\Controller\Helper\TokenRequestAuthenticator;
use App\Service\TokenRevoker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class OAuth2RevocationController extends AbstractController
{
    public function __construct(
        private readonly TokenRequestAuthenticator $tokenRequestAuthenticator,
        private readonly TokenRevoker $tokenRevoker,
    )
    {
    }

    #[Route('/oauth2/token/revoke', name: 'oauth2_token_revoke', methods: 'POST')]
    public function __invoke(Request $request): Response
    {
        // authenticate the client and confirm the token exists
        $result = $this->tokenRequestAuthenticator->authenticate($request);
        if ($result instanceof JsonResponse) {
            return $result;
        }

        // the token_type_hint parameter is optional;
        // the revoker can determine the token type automatically
        [$token, $hint, $client] = $result;

        // the revoker handles token type detection, existence, and client validation
        $this->tokenRevoker->revokeToken($token, $client->getId());

        return new Response('', Response::HTTP_OK);
    }
}