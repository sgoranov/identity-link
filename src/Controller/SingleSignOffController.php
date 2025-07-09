<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\TokenRevoker;
use sgoranov\IdentityLinkShared\Security\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

class SingleSignOffController extends AbstractController
{

    #[Route('/single-sign-off', name: 'single_sign_off', methods: ['POST', 'GET'])]
    public function index(
        TokenRevoker $tokenRevoker,
    ): Response
    {
        // This endpoint is protected — the user is guaranteed to be authenticated and must never be null.
        $user = $this->getUser();

        /** @var User $user */
        $accessToken = $user->getAccessToken();

        if (!empty($accessToken->sub)) {
            // User-based token: revoke all active (not expired and not revoked) access tokens
            // and their associated refresh tokens for the user identified by 'sub'
            $tokenRevoker->revokeByUserIdentifier($accessToken->sub);
        } elseif (!empty($accessToken->jti)) {
            // Client credentials flow: revoke only the current access token
            $tokenRevoker->revokeByTokenIdentifier($accessToken->jti);
        } else {
            // The token appears to be generated using our own keys (signature is valid),
            // but it's not recognized by the OAuth2 server — likely not issued through the proper flow.
            // This is considered an invalid or malformed token, so respond with a 400 Bad Request.
            throw new BadRequestHttpException('Unrecognized token: not associated with a valid OAuth2 flow.');
        }

        return new Response('Single sign-off completed', Response::HTTP_OK);
    }
}