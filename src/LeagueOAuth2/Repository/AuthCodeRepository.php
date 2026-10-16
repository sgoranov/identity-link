<?php
declare(strict_types=1);

namespace App\LeagueOAuth2\Repository;

use App\Entity\AuthCode;
use App\LeagueOAuth2\Entity\AuthCodeEntity;
use App\LeagueOAuth2\Entity\Mapper\AuthCodeMapper;
use App\Repository\AuthCodeRepository as Repository;
use App\Service\TokenRevoker;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use Psr\Log\LoggerInterface;

class AuthCodeRepository implements AuthCodeRepositoryInterface
{

    public function __construct(
        readonly EntityManagerInterface $entityManager,
        readonly AuthCodeMapper $authCodeMapper,
        private readonly TokenRevoker $tokenRevoker,
        private readonly LoggerInterface $logger,
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

    /**
     * Revokes the code once League has exchanged it for tokens, and detects concurrent exchanges of the same code.
     *
     * League's AuthCodeGrant::respondToAccessTokenRequest() handles an exchange in this order:
     *
     *  1. isAuthCodeRevoked() rejects the code if it is already revoked.
     *  2. ScopeRepository::finalizeScopes() decides the scopes, calling db-clients and db-users over HTTP.
     *  3. The access token is stored, then AuthCodeLinkSubscriber links it to the code.
     *  4. The refresh token is stored.
     *  5. revokeAuthCode() revokes the code.
     *
     * Two requests with the same code can both pass step 1 before either reaches step 5. Both then get tokens, and
     * neither counts as a reuse. To catch this, step 5 locks the code's row (SELECT ... FOR UPDATE):
     *
     *  - The first request to reach step 5 finds the code unrevoked, revokes it and commits.
     *  - The second waits for that commit, then finds the code revoked, so the code was used twice after all.
     *    Both requests have passed step 3 by then, so the tokens of both are linked to the code. They are all
     *    revoked, and the second request fails with invalid_grant, like a sequential reuse. The first request still
     *    returns its tokens, but they are revoked.
     */
    public function revokeAuthCode(string $codeId): void
    {
        /** @var Repository $repository */
        $repository = $this->entityManager->getRepository(AuthCode::class);
        $entity = $repository->getByIdentifier($codeId);

        $revokedByThisExchange = $this->entityManager->wrapInTransaction(function () use ($entity): bool {
            // Waits while another request holds the lock, then reloads the row as that request committed it. Reloading
            // the entity, rather than a conditional UPDATE, keeps the copy loaded in step 1 in sync with the row.
            $this->entityManager->refresh($entity, LockMode::PESSIMISTIC_WRITE);
            if ($entity->isRevoked()) {
                return false;
            }

            $entity->setIsRevoked(true);

            return true;
        });

        if (!$revokedByThisExchange) {
            // Another request exchanged the code between this request's steps 1 and 5. Either request may be the
            // attacker's, so the tokens of both are revoked, as for a sequential reuse.
            $this->tokenRevoker->revokeTokensIssuedForAuthCode($codeId);

            // Logged as an error for the same reason as in isAuthCodeRevoked().
            $this->logger->error('Authorization code reused, the tokens issued for it were revoked.', [
                'auth_code_id' => $codeId,
                'client_id' => $entity->getClientIdentifier(),
                'user_id' => $entity->getUserIdentifier(),
                'concurrent' => true,
            ]);

            throw OAuthServerException::invalidGrant('Authorization code has been revoked');
        }
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

        // A reused code means that the code leaked or that a client is broken; either needs attention. Logged as an
        // error because the production log writes lower levels only for requests that also log an error.
        $this->logger->error('Authorization code reused, the tokens issued for it were revoked.', [
            'auth_code_id' => $codeId,
            'client_id' => $entity->getClientIdentifier(),
            'user_id' => $entity->getUserIdentifier(),
            'concurrent' => false,
        ]);

        return true;
    }
}