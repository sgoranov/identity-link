<?php
declare(strict_types=1);

namespace App\Security;

use App\Entity\AuthorizationSession;
use App\Repository\AuthorizationSessionRepository;
use App\Security\Exception\ConsumedAuthorizationSessionException;
use App\Security\Exception\ExpiredAuthorizationSessionException;
use App\Security\Exception\InvalidAuthorizationSessionException;

final class AuthorizationSessionResolver
{
    public function __construct(
        private readonly AuthorizationSessionRepository $repository,
    ) {
    }

    public function resolve(string $id): AuthorizationSession
    {
        $authorizationSession = $this->repository->find($id);

        if (!$authorizationSession) {
            throw new InvalidAuthorizationSessionException();
        }

        if ($authorizationSession->getExpiresAt() < new \DateTimeImmutable()) {
            throw new ExpiredAuthorizationSessionException($authorizationSession);
        }

        if ($authorizationSession->isConsumed()) {
            throw new ConsumedAuthorizationSessionException($authorizationSession);
        }

        return $authorizationSession;
    }
}