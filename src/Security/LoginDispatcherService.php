<?php
declare(strict_types=1);

namespace App\Security;

use App\Api\Contract\UserConnectorInterface;
use App\Entity\AuthRequest;
use App\Repository\AuthRequestRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class LoginDispatcherService
{
    private array $config;

    public function __construct(
        private readonly AuthRequestRepository $authRequestRepository,
        private readonly RequestStack $requestStack,
        private readonly UserConnectorInterface $userConnector,
    )
    {
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * Determines the next step in the login flow for the current authentication request.
     *
     * The login flow is defined dynamically (via buildLoginFlow()) depending on system configuration
     *
     * If no login state is currently stored in the AuthRequest, this method will return
     * the first step in the flow.
     * Otherwise, it will return the next step in sequence, or `COMPLETED` if the flow is finished.
     *
     * @return LoginStateEnum|null The next login state to be executed, or null if the AuthRequest is invalid.
     */
    public function getNextState(): ?LoginStateEnum
    {
        $authRequest = $this->authRequestRepository->findActive($this->getAuthRequestId());
        if (!$authRequest) {
            return null;
        }

        $loginFlow = $this->buildLoginFlow($authRequest);
        $currentState = $authRequest->getLoginState();

        // If no state yet, the first step in flow is the only valid one
        if (null === $currentState) {
            return $loginFlow[0];
        }

        $currentIndex = LoginStateEnum::indexOf($currentState, $loginFlow);
        return $loginFlow[$currentIndex + 1] ?? LoginStateEnum::COMPLETED;
    }

    /**
     * Checks whether a given login state is the next valid step for the current authentication request.
     *
     * This is used to ensure that a client cannot skip required steps in the authentication process
     * (e.g., jumping directly to the 2FA verification without completing the password).
     *
     * If the AuthRequest has no current state, only the first step in the flow is allowed.
     * Otherwise, only the immediate next step in the sequence is considered valid.
     *
     * @param string $state The login state value to validate.
     * @return bool True if the given state is the next allowed step; false otherwise.
     */
    public function isStateAllowed(string $state): bool
    {
        $authRequest = $this->authRequestRepository->findActive($this->getAuthRequestId());
        if (!$authRequest) {
            return false;
        }

        $loginFlow = $this->buildLoginFlow($authRequest);

        // If no state yet, the first step in flow is the only valid one
        if ($authRequest->getLoginState() === null) {
            return $loginFlow[0]->value === $state;
        }

        // Find current position in flow
        $currentIndex = LoginStateEnum::indexOf($authRequest->getLoginState(), $loginFlow);
        if ($currentIndex === false) {
            return false; // invalid stored state
        }

        // The next valid state is the one right after the current index
        $nextState = $loginFlow[$currentIndex + 1] ?? null;

        return $nextState?->value === $state;
    }

    private function buildLoginFlow(AuthRequest $authRequest): array
    {
        $loginFlow = [LoginStateEnum::PASSWORD];

        if (!isset($this->config['login_two_fa_enabled'])) {
            throw new \RuntimeException('login_two_fa_enabled is not set in config');
        }

        if ($this->config['login_two_fa_enabled'] && $this->isTwoFaEnabledForUser($authRequest)) {
            $loginFlow[] = LoginStateEnum::TWO_FACTOR_INITIATE;
            $loginFlow[] = LoginStateEnum::TWO_FACTOR_COMPLETE;
        }

        $loginFlow[] = LoginStateEnum::COMPLETED;

        return $loginFlow;
    }

    private function isTwoFaEnabledForUser(AuthRequest $authRequest): bool
    {
        $userIdentifier = $authRequest->getUserIdentifier();
        if ($userIdentifier === null) {
            return true;
        }

        return $this->userConnector->getUserById($userIdentifier)?->twoFaEnabled() ?? true;
    }

    private function getAuthRequestId(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        return $request->attributes->get('id');
    }
}
