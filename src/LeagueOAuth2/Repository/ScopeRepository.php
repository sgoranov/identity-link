<?php
declare(strict_types=1);

namespace App\LeagueOAuth2\Repository;

use App\Api\Contract\ClientConnectorInterface;
use App\Api\Contract\UserConnectorInterface;
use App\LeagueOAuth2\Entity\ClientEntityInterface as IdentityLinkClientEntityInterface;
use App\LeagueOAuth2\Entity\ScopeEntity;
use App\Repository\AuthCodeRepository;
use App\Security\Authorization\AuthorizationRegistry;
use App\Security\Authorization\Loader\AuthorizationLoaderInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;

class ScopeRepository implements ScopeRepositoryInterface
{
    private readonly AuthorizationRegistry $authorizationRegistry;

    public function __construct(
        private readonly ClientConnectorInterface $clientConnector,
        private readonly UserConnectorInterface $userConnector,
        private readonly AuthCodeRepository $authCodeRepository,
        AuthorizationLoaderInterface $authorizationLoader
    ) {
        $this->authorizationRegistry = $authorizationLoader->load();
    }

    public function getScopeEntityByIdentifier(string $identifier): ?ScopeEntityInterface
    {
        if (!$this->authorizationRegistry->containsScopeOrAlias($identifier)) {
            return null;
        }

        return new ScopeEntity($identifier);
    }

    public function finalizeScopes(
        array $scopes,
        string $grantType,
        ClientEntityInterface $clientEntity,
        ?string $userIdentifier = null,
        ?string $authCodeId = null
    ): array
    {
        if (!$clientEntity instanceof IdentityLinkClientEntityInterface) {
            throw new \LogicException(sprintf(
                'Client entity must implement %s.',
                IdentityLinkClientEntityInterface::class,
            ));
        }

        $audience = $clientEntity->getAudience();
        $availableScopes = $this->authorizationRegistry->expandScopes(
            $audience,
            array_map('strval', $scopes)
        );

        if ($authCodeId !== null) {
            // Restrict token scopes to those granted by the authorization code. For example,
            // if the authorization request granted A, B, and C, a later request for A, B, C, and D drops D.
            $authCode = $this->authCodeRepository->getByIdentifier($authCodeId);
            $availableScopes = $this->filterScopes(
                $availableScopes,
                $this->authorizationRegistry->expandScopes(
                    $audience,
                    array_map('trim', json_decode($authCode->getScopes(), true))
                ),
            );
        }

        // Restrict the remaining scopes to those assigned to the client.
        $availableScopes = $this->filterScopes(
            $availableScopes,
            $this->authorizationRegistry->expandScopes(
                $audience,
                $this->clientConnector->getScopes($clientEntity->getIdentifier(), $audience)
            ),
        );

        if ($userIdentifier !== null) {
            // For non-client-credentials flows, restrict the remaining scopes to those assigned to the user.
            $availableScopes = $this->filterScopes(
                $availableScopes,
                $this->authorizationRegistry->expandScopes(
                    $audience,
                    $this->userConnector->getScopes($userIdentifier, $audience)
                ),
            );
        }

        return array_map(
            static fn (string $scope): ScopeEntityInterface => new ScopeEntity($scope),
            $availableScopes,
        );
    }

    private function filterScopes(array $scopes, array $availableScopes): array
    {
        return array_values(array_filter(
            $scopes,
            static fn (string $scope): bool => in_array($scope, $availableScopes, true),
        ));
    }
}
