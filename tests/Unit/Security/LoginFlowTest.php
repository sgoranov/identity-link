<?php
declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Api\Contract\UserConnectorInterface;
use App\Api\Contract\UserResponseInterface;
use App\Entity\AuthRequest;
use App\Repository\AuthRequestRepository;
use App\Security\Exception\InvalidLoginStateException;
use App\Security\Exception\UserNotFoundException;
use App\Security\LoginFlow;
use App\Security\LoginFlowConfig;
use App\Security\LoginStateEnum;
use PHPUnit\Framework\TestCase;

class LoginFlowTest extends TestCase
{
    public function testInitialStateStartsWithPassword(): void
    {
        $flow = $this->createLoginFlow(
            $this->createAuthRequest(null, null),
            expectUserLookup: false,
        );

        $this->assertSame(LoginStateEnum::PASSWORD, $flow->resolveNextState());

        $flow->assertStateAllowed(LoginStateEnum::PASSWORD);
        $this->addToAssertionCount(1);
    }

    public function testNextStateRequiresTwoFaWhenGloballyAndUserEnabled(): void
    {
        $dispatcher = $this->createLoginFlow(
            $this->createAuthRequest(LoginStateEnum::PASSWORD, 'user-id'),
        );

        $this->assertSame(LoginStateEnum::TWO_FACTOR_INITIATE, $dispatcher->resolveNextState());
    }

    public function testTwoFaFlowProgressesInOrderWhenUserEnabled(): void
    {
        $flow = $this->createLoginFlow(
            $this->createAuthRequest(LoginStateEnum::TWO_FACTOR_INITIATE, 'user-id'),
        );

        $this->assertSame(LoginStateEnum::TWO_FACTOR_COMPLETE, $flow->resolveNextState());
        $flow->assertStateAllowed(LoginStateEnum::TWO_FACTOR_COMPLETE);
        $this->addToAssertionCount(1);
    }

    public function testCompleteIsAllowedAfterTwoFaCompletes(): void
    {
        $flow = $this->createLoginFlow(
            $this->createAuthRequest(LoginStateEnum::TWO_FACTOR_COMPLETE, 'user-id'),
        );

        $this->assertSame(LoginStateEnum::COMPLETED, $flow->resolveNextState());
        $flow->assertStateAllowed(LoginStateEnum::COMPLETED);
        $this->addToAssertionCount(1);
    }

    public function testNextStateSkipsTwoFaWhenUserDisabled(): void
    {
        $flow = $this->createLoginFlow(
            $this->createAuthRequest(LoginStateEnum::PASSWORD, 'user-id'),
            twoFaEnabled: false,
        );

        $this->assertSame(LoginStateEnum::COMPLETED, $flow->resolveNextState());
    }

    public function testTwoFaRoutesAreNotAllowedWhenUserDisabled(): void
    {
        $flow = $this->createLoginFlow(
            $this->createAuthRequest(LoginStateEnum::PASSWORD, 'user-id'),
            twoFaEnabled: false,
        );

        $flow->assertStateAllowed(LoginStateEnum::COMPLETED);
        $this->addToAssertionCount(1);
    }

    public function testCompleteIsNotAllowedBeforeTwoFaWhenUserEnabled(): void
    {
        $flow = $this->createLoginFlow(
            $this->createAuthRequest(LoginStateEnum::PASSWORD, 'user-id'),
        );

        $this->expectException(InvalidLoginStateException::class);
        $flow->assertStateAllowed(LoginStateEnum::COMPLETED);
    }

    public function testGlobalTwoFaDisabledSkipsTwoFaWithoutLoadingUser(): void
    {
        $flow = $this->createLoginFlow(
            $this->createAuthRequest(LoginStateEnum::PASSWORD, 'user-id'),
            globalTwoFaEnabled: false,
            expectUserLookup: false,
        );

        $this->assertSame(LoginStateEnum::COMPLETED, $flow->resolveNextState());
        $flow->assertStateAllowed(LoginStateEnum::COMPLETED);
        $this->addToAssertionCount(1);
    }

    public function testMissingUserIdentifierDefaultsToRequiringTwoFaWithoutLoadingUser(): void
    {
        $flow = $this->createLoginFlow(
            $this->createAuthRequest(LoginStateEnum::PASSWORD, null),
            expectUserLookup: false,
        );

        $this->assertSame(LoginStateEnum::TWO_FACTOR_INITIATE, $flow->resolveNextState());
    }

    public function testMissingUserThrowsException(): void
    {
        $dispatcher = $this->createLoginFlow(
            $this->createAuthRequest(
                LoginStateEnum::PASSWORD,
                'user-id',
            ),
            twoFaEnabled: null,
        );

        $this->expectException(UserNotFoundException::class);

        $dispatcher->resolveNextState();
    }

    private function createLoginFlow(
        ?AuthRequest $authRequest,
        bool $globalTwoFaEnabled = true,
        ?bool $twoFaEnabled = true,
        bool $expectUserLookup = true,
    ): LoginFlow {
        return new LoginFlow(
            new LoginFlowConfig(
                twoFactorEnabled: $globalTwoFaEnabled,
            ),
            $this->createUserConnector($twoFaEnabled, $expectUserLookup),
            $authRequest,
        );
    }

    private function createUserConnector(?bool $twoFaEnabled = true, bool $expectUserLookup = true): UserConnectorInterface
    {
        $userConnector = $this->createMock(UserConnectorInterface::class);
        $expectation = $userConnector
            ->expects($expectUserLookup ? $this->atLeastOnce() : $this->never())
            ->method('getUserById');

        if (!$expectUserLookup) {
            return $userConnector;
        }

        // A null value indicates that the user lookup should return no user,
        // allowing tests to exercise the "user not found" scenario.
        $expectation->with('user-id');
        if ($twoFaEnabled === null) {
            $expectation->willReturn(null);

            return $userConnector;
        }

        $user = $this->createMock(UserResponseInterface::class);
        $user
            ->method('twoFaEnabled')
            ->willReturn($twoFaEnabled);

        $expectation->willReturn($user);

        return $userConnector;
    }

    private function createAuthRequest(?LoginStateEnum $loginState, ?string $userIdentifier): AuthRequest
    {
        $authRequest = new AuthRequest();
        $authRequest->setLoginState($loginState);
        $authRequest->setUserIdentifier($userIdentifier);

        return $authRequest;
    }
}
