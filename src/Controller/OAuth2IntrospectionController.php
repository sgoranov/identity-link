<?php
declare(strict_types=1);

namespace App\Controller;

use App\Controller\Helper\TokenRequestAuthenticator;
use App\Service\TokenIntrospector;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class OAuth2IntrospectionController extends AbstractController
{
    public function __construct(
        private readonly TokenIntrospector $introspector,
        private readonly TokenRequestAuthenticator $tokenRequestAuthenticator,
    )
    {
    }

    #[Route('/oauth2/token/introspect', name: 'oauth2_token_introspect', methods: 'POST')]
    public function __invoke(Request $request): JsonResponse
    {
        $result = $this->tokenRequestAuthenticator->authenticate($request);
        if ($result instanceof JsonResponse) {
            return $result;
        }

        // the token_type_hint parameter is optional;
        // we can determine the token type automatically
        [$token, $hint, $client] = $result;

        return new JsonResponse(
            $this->introspector->introspect($token, $client->getId())
        );
    }
}