<?php
declare(strict_types=1);

namespace App\Security;

use App\Entity\AuthRequest;
use App\Repository\AuthRequestRepository;
use App\Security\Exception\ConsumedAuthRequestException;
use App\Security\Exception\ExpiredAuthRequestException;
use App\Security\Exception\InvalidAuthRequestException;

final class AuthRequestResolver
{
    public function __construct(
        private readonly AuthRequestRepository $repository,
    ) {
    }

    public function resolve(string $id): AuthRequest
    {
        $authRequest = $this->repository->find($id);

        if (!$authRequest) {
            throw new InvalidAuthRequestException();
        }

        if ($authRequest->getExpiresAt() < new \DateTimeImmutable()) {
            throw new ExpiredAuthRequestException($authRequest);
        }

        if ($authRequest->isConsumed()) {
            throw new ConsumedAuthRequestException($authRequest);
        }

        return $authRequest;
    }
}