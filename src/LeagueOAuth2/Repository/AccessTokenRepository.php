<?php
declare(strict_types=1);

namespace App\LeagueOAuth2\Repository;

use App\Api\Contract\ClientConnectorInterface;
use App\Api\Contract\UserConnectorInterface;
use App\Entity\AccessToken;
use App\LeagueOAuth2\Entity\AccessTokenEntity;
use App\LeagueOAuth2\Entity\Mapper\AccessTokenMapper;
use App\Repository\AccessTokenRepository as Repository;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;

class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    private bool $includeGroupsClaim;
    private int $groupsClaimLimit;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AccessTokenMapper $accessTokenMapper,
        private readonly ClientConnectorInterface $clientConnector,
        private readonly UserConnectorInterface $userConnector,
    )
    {
    }

    public function configureJwtGroupsClaims(bool $includeGroupsClaim, int $groupsClaimLimit): void
    {
        $this->includeGroupsClaim = $includeGroupsClaim;
        $this->groupsClaimLimit = $groupsClaimLimit;
    }

    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, $userIdentifier = null): AccessTokenEntity
    {
        $token = new AccessTokenEntity();
        $token->setClient($clientEntity);
        $token->setUserIdentifier($userIdentifier);
        $token->setScopes($scopes);
        $token->setIsRevoked(false);

        if ($this->includeGroupsClaim) {
            $token->setGroups($this->getGroups($clientEntity, $userIdentifier));
        }

        return $token;
    }

    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        $this->entityManager->persist($this->accessTokenMapper->toDoctrineEntity($accessTokenEntity));
        $this->entityManager->flush();
    }

    public function revokeAccessToken(string $tokenId): void
    {
        /** @var Repository $repository */
        $repository = $this->entityManager->getRepository(AccessToken::class);
        $entity = $repository->getByIdentifier($tokenId);
        $entity->setIsRevoked(true);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function isAccessTokenRevoked(string $tokenId): bool
    {
        /** @var Repository $repository */
        $repository = $this->entityManager->getRepository(AccessToken::class);
        $entity = $repository->getByIdentifier($tokenId);

        return $entity->isRevoked();
    }

    private function getGroups(ClientEntityInterface $clientEntity, ?string $userIdentifier = null): array
    {
        if ($userIdentifier !== null) {
            $response = $this->userConnector->getGroups($userIdentifier, $this->groupsClaimLimit);
        } else {
            $response = $this->clientConnector->getGroups($clientEntity->getIdentifier(), $this->groupsClaimLimit);
        }

        $groups = [];
        foreach ($response->getGroups() as $item) {
            $groups[] = $item['name'];
        }

        return $groups;
    }
}