<?php
declare(strict_types=1);

namespace App\Controller\Helper;

use App\Service\ClientAuthenticator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class TokenRequestAuthenticator
{
    public function __construct(
        private readonly ClientAuthenticator $clientAuthenticator,
    )
    {
    }

    public function authenticate(Request $request): array|JsonResponse
    {
        $token = $request->request->get('token');
        $hint = $request->request->get('token_type_hint');

        if (!$token) {
            return new JsonResponse([
                'error' => 'invalid_request',
                'error_description' => 'Missing token parameter'
            ], Response::HTTP_BAD_REQUEST);
        }

        list($clientId, $clientSecret) = $this->clientAuthenticator->extractCredentials();
        $client = ($clientId !== null)
            ? $this->clientAuthenticator->authenticate($clientId, $clientSecret)
            : null;

        if ($client === null) {
            return new JsonResponse([
                'error' => 'invalid_client',
                'error_description' => 'Missing or invalid client credentials'
            ], Response::HTTP_UNAUTHORIZED);
        }

        return [$token, $hint, $client];
    }
}