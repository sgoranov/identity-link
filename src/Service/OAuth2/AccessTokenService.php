<?php
declare(strict_types=1);

namespace App\Service\OAuth2;

use App\Entity\AccessToken;
use App\Model\OAuth2\AccessTokenModel;
use App\ModelMapper\AccessTokenMapper;
use App\Repository\AccessTokenRepository;
use App\Service\Api\ClientConnectorInterface;
use App\Service\Api\UserConnectorInterface;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;

class AccessTokenService implements AccessTokenRepositoryInterface
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

    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, $userIdentifier = null): AccessTokenModel
    {
        $token = new AccessTokenModel();
        $token->setClient($clientEntity);
        $token->setUserIdentifier($userIdentifier);
        $token->setScopes($scopes);
        $token->setIsRevoked(false);

        if ($this->includeGroupsClaim) {
            $token->setGroups($this->getGroups($clientEntity, $userIdentifier));
        }

        return $token;
    }

    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity)
    {
        $this->entityManager->persist($this->accessTokenMapper->toEntity($accessTokenEntity));
        $this->entityManager->flush();
    }

    public function revokeAccessToken($tokenId)
    {
        /** @var AccessTokenRepository $repository */
        $repository = $this->entityManager->getRepository(AccessToken::class);
        $entity = $repository->getByIdentifier($tokenId);
        $entity->setIsRevoked(true);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function isAccessTokenRevoked($tokenId): bool
    {
        /** @var AccessTokenRepository $repository */
        $repository = $this->entityManager->getRepository(AccessToken::class);
        $entity = $repository->getByIdentifier($tokenId);

        return $entity->isRevoked();
    }

    private function getGroups(ClientEntityInterface $clientEntity, string $userIdentifier = null): array
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