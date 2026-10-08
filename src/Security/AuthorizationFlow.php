<?php
declare(strict_types=1);

namespace App\Security;

use App\Api\Contract\UserConnectorInterface;
use App\Entity\AuthorizationSession;
use App\Security\Exception\InvalidAuthorizationStateException;
use App\Security\Exception\UserNotFoundException;

class AuthorizationFlow
{

    public function __construct(
        private readonly AuthorizationFlowConfig $authorizationFlowConfig,
        private readonly UserConnectorInterface $userConnector,
        private readonly AuthorizationSession $authorizationSession,
    )
    {
    }

    /**
     * Determines the next step in the authorization flow for the current authorization session.
     *
     * The authorization flow is defined dynamically (via buildAuthorizationFlow()) depending on system configuration
     *
     * If no authorization state is currently stored in the AuthorizationSession, this method will return
     * the first step in the flow.
     * Otherwise, it will return the next step in sequence, or `COMPLETED` if the flow is finished.
     *
     * @return AuthorizationStateEnum|null The next authorization state to be executed, or null if the AuthorizationSession is invalid.
     */
    public function resolveNextState(): ?AuthorizationStateEnum
    {
        $authorizationFlow = $this->buildAuthorizationFlow();
        $currentState = $this->authorizationSession->getAuthorizationState();

        // If no state yet, the first step in flow is the only valid one
        if (null === $currentState) {
            return $authorizationFlow[0];
        }

        $currentIndex = AuthorizationStateEnum::indexOf($currentState, $authorizationFlow);
        return $authorizationFlow[$currentIndex + 1] ?? AuthorizationStateEnum::COMPLETED;
    }

    /**
     * Ensures that the given authorization state is the next valid step in the current
     * authentication flow.
     *
     * This prevents clients from skipping or repeating authentication steps
     * (for example, attempting to complete two-factor authentication before
     * successfully completing the password step).
     *
     * If the authorization session has no current authorization state, only the first
     * state in the configured authorization flow is considered valid. Otherwise, only
     * the immediate next state in the flow is allowed.
     */
    public function assertStateAllowed(AuthorizationStateEnum $state): void
    {
        $authorizationFlow = $this->buildAuthorizationFlow();

        if ($this->authorizationSession->getAuthorizationState() === null) {
            // If no state yet, the first step in flow is the only valid one
            if ($authorizationFlow[0] !== $state) {
                throw new InvalidAuthorizationStateException($this->authorizationSession, $state);
            }
        } else {
            // Find current position in flow
            $currentIndex = AuthorizationStateEnum::indexOf($this->authorizationSession->getAuthorizationState(), $authorizationFlow);
            if ($currentIndex === false) {
                throw new InvalidAuthorizationStateException($this->authorizationSession, $state);
            }

            // The next valid state is the one right after the current index
            $nextState = $authorizationFlow[$currentIndex + 1] ?? null;
            if ($nextState !== $state) {
                throw new InvalidAuthorizationStateException($this->authorizationSession, $state);
            }
        }
    }

    /**
     * Ensures that the login flow for the current authorization session has
     * been completed and the user is identified.
     *
     * The steps after the login (consent and issuing the authorization code)
     * rely on the user stored in the AuthorizationSession, so they must not be reached
     * before every login step has passed.
     */
    public function assertCompleted(): void
    {
        if ($this->authorizationSession->getAuthorizationState() !== AuthorizationStateEnum::COMPLETED
            || !$this->authorizationSession->getUserIdentifier()) {
            throw new InvalidAuthorizationStateException($this->authorizationSession, AuthorizationStateEnum::COMPLETED);
        }
    }

    private function buildAuthorizationFlow(): array
    {
        $authorizationFlow = [AuthorizationStateEnum::PASSWORD];

        if ($this->authorizationFlowConfig->twoFactorEnabled && $this->isTwoFaEnabledForUser()) {
            $authorizationFlow[] = AuthorizationStateEnum::TWO_FACTOR_INITIATE;
            $authorizationFlow[] = AuthorizationStateEnum::TWO_FACTOR_COMPLETE;
        }

        $authorizationFlow[] = AuthorizationStateEnum::COMPLETED;

        return $authorizationFlow;
    }

    private function isTwoFaEnabledForUser(): bool
    {
        $userIdentifier = $this->authorizationSession->getUserIdentifier();
        if (!$userIdentifier) {
            // The user has not been identified yet (the password step has not
            // completed). Assume that 2FA is enabled, so the login flow includes
            // the password step first. Once the user is identified, the actual
            // 2FA configuration will be determined.
            return true;
        }

        $user = $this->userConnector->getUserById($userIdentifier);
        if ($user === null) {
            // The user was resolved earlier in the login flow but no longer exists.
            // Abort the authentication and let AuthorizationFlowExceptionSubscriber present
            // a user-friendly error page.
            throw new UserNotFoundException($this->authorizationSession, $userIdentifier);
        }

        return $user->twoFaEnabled();
    }
}
