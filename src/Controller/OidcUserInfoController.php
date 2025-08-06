<?php
declare(strict_types=1);

namespace App\Controller;

use App\Api\Contract\UserConnectorInterface;
use OpenIDConnectServer\ClaimExtractor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OidcUserInfoController extends AbstractController
{
    #[Route('/user-info', name: 'oidc_user_info', methods: ['GET', 'POST'])]
    public function index(UserConnectorInterface $userConnector, ClaimExtractor $claimExtractor): Response
    {
        // This endpoint is protected — the user is guaranteed to be authenticated and must never be null.
        $user = $this->getUser();
        $accessToken = (array) $user->getAccessToken();

        if (empty($accessToken['sub'])) {
            return new JsonResponse(
                [
                    'error' => 'invalid_request',
                    'error_description' => "The 'sub' claim is required and cannot be empty."
                ],
                Response::HTTP_BAD_REQUEST,
                ['WWW-Authenticate' => 'Bearer error="invalid_request", error_description="The \'sub\' claim is required and cannot be empty."']
            );
        }

        $user = $userConnector->getUserById($accessToken['sub']);
        if (!$user) {
            return new JsonResponse(
                [
                    'error' => 'invalid_token',
                    'error_description' => 'The access token is invalid or missing required claims.'
                ],
                Response::HTTP_UNAUTHORIZED,
                ['WWW-Authenticate' => 'Bearer realm="user-info", error="invalid_token", error_description="The access token is invalid or missing required claims."']
            );
        }

        $data = $claimExtractor->extract($accessToken['scopes'], $user->getClaims());
        $data['sub'] = $user->getId();

        return new JsonResponse($data);
    }
}