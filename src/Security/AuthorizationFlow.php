<?php
declare(strict_types=1);

namespace App\Security;

use App\Api\Contract\UserConnectorInterface;
use App\Entity\AuthRequest;
use App\Security\Exception\InvalidLoginStateException;
use App\Security\Exception\UserNotFoundException;

class AuthorizationFlow
{

    public function __construct(
        private readonly AuthorizationFlowConfig $authorizationFlowConfig,
        private readonly UserConnectorInterface $userConnector,
        private readonly AuthRequest $authRequest,
    )
    {
    }

    /**
     * Determines the next step in the authorization flow for the current authentication request.
     *
     * The authorization flow is defined dynamically (via buildAuthorizationFlow()) depending on system configuration
     *
     * If no login state is currently stored in the AuthRequest, this method will return
     * the first step in the flow.
     * Otherwise, it will return the next step in sequence, or `COMPLETED` if the flow is finished.
     *
     * @return LoginStateEnum|null The next login state to be executed, or null if the AuthRequest is invalid.
     */
    public function resolveNextState(): ?LoginStateEnum
    {
        $authorizationFlow = $this->buildAuthorizationFlow();
        $currentState = $this->authRequest->getLoginState();

        // If no state yet, the first step in flow is the only valid one
        if (null === $currentState) {
            return $authorizationFlow[0];
        }

        $currentIndex = LoginStateEnum::indexOf($currentState, $authorizationFlow);
        return $authorizationFlow[$currentIndex + 1] ?? LoginStateEnum::COMPLETED;
    }

    /**
     * Ensures that the given login state is the next valid step in the current
     * authentication flow.
     *
     * This prevents clients from skipping or repeating authentication steps
     * (for example, attempting to complete two-factor authentication before
     * successfully completing the password step).
     *
     * If the authentication request has no current login state, only the first
     * state in the configured authorization flow is considered valid. Otherwise, only
     * the immediate next state in the flow is allowed.
     */
    public function assertStateAllowed(LoginStateEnum $state): void
    {
        $authorizationFlow = $this->buildAuthorizationFlow();

        if ($this->authRequest->getLoginState() === null) {
            // If no state yet, the first step in flow is the only valid one
            if ($authorizationFlow[0] !== $state) {
                throw new InvalidLoginStateException($this->authRequest, $state);
            }
        } else {
            // Find current position in flow
            $currentIndex = LoginStateEnum::indexOf($this->authRequest->getLoginState(), $authorizationFlow);
            if ($currentIndex === false) {
                throw new InvalidLoginStateException($this->authRequest, $state);
            }

            // The next valid state is the one right after the current index
            $nextState = $authorizationFlow[$currentIndex + 1] ?? null;
            if ($nextState !== $state) {
                throw new InvalidLoginStateException($this->authRequest, $state);
            }
        }
    }

    /**
     * Ensures that the login flow for the current authentication request has
     * been completed and the user is identified.
     *
     * The steps after the login (consent and issuing the authorization code)
     * rely on the user stored in the AuthRequest, so they must not be reached
     * before every login step has passed.
     */
    public function assertCompleted(): void
    {
        if ($this->authRequest->getLoginState() !== LoginStateEnum::COMPLETED
            || !$this->authRequest->getUserIdentifier()) {
            throw new InvalidLoginStateException($this->authRequest, LoginStateEnum::COMPLETED);
        }
    }

    private function buildAuthorizationFlow(): array
    {
        $authorizationFlow = [LoginStateEnum::PASSWORD];

        if ($this->authorizationFlowConfig->twoFactorEnabled && $this->isTwoFaEnabledForUser()) {
            $authorizationFlow[] = LoginStateEnum::TWO_FACTOR_INITIATE;
            $authorizationFlow[] = LoginStateEnum::TWO_FACTOR_COMPLETE;
        }

        $authorizationFlow[] = LoginStateEnum::COMPLETED;

        return $authorizationFlow;
    }

    private function isTwoFaEnabledForUser(): bool
    {
        $userIdentifier = $this->authRequest->getUserIdentifier();
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
            throw new UserNotFoundException($this->authRequest, $userIdentifier);
        }

        return $user->twoFaEnabled();
    }
}
