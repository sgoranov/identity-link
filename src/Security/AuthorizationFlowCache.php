<?php
declare(strict_types=1);

namespace App\Security;

/**
 * The steps of an authorization flow, kept by AuthorizationFlow so they are built
 * only once while handling a request.
 *
 * @internal Used only by AuthorizationFlow
 */
final class AuthorizationFlowCache
{
    /** @param list<AuthorizationStateEnum> $steps */
    public function __construct(
        public readonly array $steps,
        private readonly ?string $userIdentifier,
    ) {
    }

    /**
     * The steps depend on the user (2FA is included only when the user has it enabled),
     * so they are only valid for the user they were built for.
     *
     * Before the password step the user is unknown and 2FA is assumed. Once the password
     * step identifies the user, the steps must be built again for that user. Otherwise a
     * flow used before and after the password step (as in the password step itself) would
     * send users without 2FA to the 2FA step.
     */
    public function isValidFor(?string $userIdentifier): bool
    {
        return $this->userIdentifier === $userIdentifier;
    }
}
