<?php
declare(strict_types=1);

namespace App\Security;

use App\Api\Contract\ClientConnectorInterface;
use App\Api\Contract\UserConnectorInterface;
use App\Entity\AuthorizationSession;
use App\Security\Exception\ClientNotFoundException;
use App\Security\Exception\InvalidAuthorizationStateException;
use App\Security\Exception\UserNotFoundException;

class AuthorizationFlow
{
    private ?AuthorizationFlowCache $cache = null;

    public function __construct(
        private readonly AuthorizationFlowConfig $authorizationFlowConfig,
        private readonly UserConnectorInterface $userConnector,
        private readonly ClientConnectorInterface $clientConnector,
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
     * Otherwise, it will return the next step in sequence.
     *
     * @throws InvalidAuthorizationStateException when the stored state is not part of the flow
     * @throws \LogicException when the flow is already finished (the authorization code has been issued)
     */
    public function resolveNextState(): AuthorizationStateEnum
    {
        $authorizationFlow = $this->getAuthorizationFlow();
        $currentState = $this->authorizationSession->getAuthorizationState();

        // If no state yet, the first step in flow is the only valid one
        if (null === $currentState) {
            return $authorizationFlow[0];
        }

        $currentIndex = AuthorizationStateEnum::indexOf($currentState, $authorizationFlow);
        if ($currentIndex === false) {
            // The stored state is not part of the flow, e.g. 2FA or consent was disabled
            // while the flow was in progress
            throw new InvalidAuthorizationStateException($this->authorizationSession, $currentState);
        }

        $nextState = $authorizationFlow[$currentIndex + 1] ?? null;
        if ($nextState === null) {
            // Issuing the authorization code is the last step and consumes the session,
            // so asking for a step after it is a programming error
            throw new \LogicException(sprintf('The authorization flow has no step after "%s".', $currentState->name));
        }

        return $nextState;
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
        $authorizationFlow = $this->getAuthorizationFlow();

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
     * Returns the steps of the authorization flow, building them on first use.
     *
     * @return list<AuthorizationStateEnum>
     */
    private function getAuthorizationFlow(): array
    {
        // Rebuild when the user has changed, see AuthorizationFlowCache::isValidFor()
        $userIdentifier = $this->authorizationSession->getUserIdentifier();
        if (!$this->cache?->isValidFor($userIdentifier)) {
            $this->cache = new AuthorizationFlowCache($this->buildAuthorizationFlow(), $userIdentifier);
        }

        return $this->cache->steps;
    }

    /** @return list<AuthorizationStateEnum> */
    private function buildAuthorizationFlow(): array
    {
        $authorizationFlow = [AuthorizationStateEnum::PASSWORD];

        if ($this->authorizationFlowConfig->twoFactorEnabled && $this->isTwoFaEnabledForUser()) {
            $authorizationFlow[] = AuthorizationStateEnum::TWO_FACTOR_INITIATE;
            $authorizationFlow[] = AuthorizationStateEnum::TWO_FACTOR_COMPLETE;
        }

        if ($this->isConsentRequiredForClient()) {
            $authorizationFlow[] = AuthorizationStateEnum::CONSENT;
        }

        $authorizationFlow[] = AuthorizationStateEnum::COMPLETED;

        // Issuing the authorization code is the last step, so it is reached only after
        // every step before it (including 2FA and consent, when required) has passed
        $authorizationFlow[] = AuthorizationStateEnum::AUTHORIZATION_CODE;

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

    private function isConsentRequiredForClient(): bool
    {
        $clientIdentifier = $this->authorizationSession->getClientId();
        $client = $this->clientConnector->getClientById($clientIdentifier);
        if ($client === null) {
            // The client was validated when the authorization session was created
            // but no longer exists. Abort and let AuthorizationFlowExceptionSubscriber
            // present a user-friendly error page.
            throw new ClientNotFoundException($this->authorizationSession, $clientIdentifier);
        }

        return $client->isConsentRequired();
    }
}
