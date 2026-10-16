<?php
declare(strict_types=1);

namespace App\LeagueOAuth2\Repository;

use App\Entity\AuthCode;
use App\LeagueOAuth2\Entity\AuthCodeEntity;
use App\LeagueOAuth2\Entity\Mapper\AuthCodeMapper;
use App\Repository\AuthCodeRepository as Repository;
use App\Service\TokenRevoker;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;

class AuthCodeRepository implements AuthCodeRepositoryInterface
{

    public function __construct(
        readonly EntityManagerInterface $entityManager,
        readonly AuthCodeMapper $authCodeMapper,
        private readonly TokenRevoker $tokenRevoker,
    )
    {
    }

    public function getNewAuthCode(): AuthCodeEntity
    {
        return new AuthCodeEntity();
    }

    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        $this->entityManager->persist($this->authCodeMapper->toDoctrineEntity($authCodeEntity));
        $this->entityManager->flush();
    }

    public function revokeAuthCode(string $codeId): void
    {
        /** @var Repository $repository */
        $repository = $this->entityManager->getRepository(AuthCode::class);
        $entity = $repository->getByIdentifier($codeId);
        $entity->setIsRevoked(true);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function isAuthCodeRevoked(string $codeId): bool
    {
        /** @var Repository $repository */
        $repository = $this->entityManager->getRepository(AuthCode::class);
        $entity = $repository->getByIdentifier($codeId);
        if (!$entity->isRevoked()) {
            return false;
        }

        // League revokes a code only after exchanging it, and calls this only before an exchange, so a revoked code
        // is being reused. RFC 6749 §4.1.2: the tokens previously issued for it should be revoked.
        $this->tokenRevoker->revokeTokensIssuedForAuthCode($codeId);

        return true;
    }
}