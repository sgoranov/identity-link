<?php
declare(strict_types=1);

namespace App\Controller;

use App\Security\Authorization\AuthorizationRegistry;
use App\Security\Authorization\Loader\AuthorizationLoaderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuthorizationMetadataController extends AbstractController
{
    private readonly AuthorizationRegistry $authorizationRegistry;

    public function __construct(AuthorizationLoaderInterface $authorizationLoader)
    {
        $this->authorizationRegistry = $authorizationLoader->load();
    }

    #[Route('/api/v1/authorization/audiences', name: 'authorization_audiences', methods: ['GET'])]
    public function audiences(): JsonResponse
    {
        return new JsonResponse([
            'audiences' => $this->authorizationRegistry->getAudiences(),
        ]);
    }

    #[Route('/api/v1/authorization/scopes', name: 'authorization_scopes', methods: ['GET'])]
    public function scopes(Request $request): JsonResponse
    {
        $audience = $request->query->get('audience');
        if (!is_string($audience) || '' === $audience) {
            return new JsonResponse(
                ['error' => 'The "audience" query parameter is required.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        try {
            return new JsonResponse($this->authorizationRegistry->getScopesAndAliases($audience));
        } catch (\InvalidArgumentException) {
            return new JsonResponse(
                ['error' => sprintf('Unknown audience "%s".', $audience)],
                Response::HTTP_NOT_FOUND,
            );
        }
    }
}
