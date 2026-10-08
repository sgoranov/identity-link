<?php
declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Api\Contract\UserConnectorInterface;
use App\Api\Contract\UserResponseInterface;
use App\Entity\AuthorizationSession;
use App\Repository\AuthorizationSessionRepository;
use App\Security\Exception\InvalidLoginStateException;
use App\Security\Exception\UserNotFoundException;
use App\Security\AuthorizationFlow;
use App\Security\AuthorizationFlowConfig;
use App\Security\LoginStateEnum;
use PHPUnit\Framework\TestCase;

class AuthorizationFlowTest extends TestCase
{
    public function testInitialStateStartsWithPassword(): void
    {
        $flow = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(null, null),
            expectUserLookup: false,
        );

        $this->assertSame(LoginStateEnum::PASSWORD, $flow->resolveNextState());

        $flow->assertStateAllowed(LoginStateEnum::PASSWORD);
        $this->addToAssertionCount(1);
    }

    public function testNextStateRequiresTwoFaWhenGloballyAndUserEnabled(): void
    {
        $dispatcher = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(LoginStateEnum::PASSWORD, 'user-id'),
        );

        $this->assertSame(LoginStateEnum::TWO_FACTOR_INITIATE, $dispatcher->resolveNextState());
    }

    public function testTwoFaFlowProgressesInOrderWhenUserEnabled(): void
    {
        $flow = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(LoginStateEnum::TWO_FACTOR_INITIATE, 'user-id'),
        );

        $this->assertSame(LoginStateEnum::TWO_FACTOR_COMPLETE, $flow->resolveNextState());
        $flow->assertStateAllowed(LoginStateEnum::TWO_FACTOR_COMPLETE);
        $this->addToAssertionCount(1);
    }

    public function testCompleteIsAllowedAfterTwoFaCompletes(): void
    {
        $flow = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(LoginStateEnum::TWO_FACTOR_COMPLETE, 'user-id'),
        );

        $this->assertSame(LoginStateEnum::COMPLETED, $flow->resolveNextState());
        $flow->assertStateAllowed(LoginStateEnum::COMPLETED);
        $this->addToAssertionCount(1);
    }

    public function testNextStateSkipsTwoFaWhenUserDisabled(): void
    {
        $flow = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(LoginStateEnum::PASSWORD, 'user-id'),
            twoFaEnabled: false,
        );

        $this->assertSame(LoginStateEnum::COMPLETED, $flow->resolveNextState());
    }

    public function testTwoFaRoutesAreNotAllowedWhenUserDisabled(): void
    {
        $flow = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(LoginStateEnum::PASSWORD, 'user-id'),
            twoFaEnabled: false,
        );

        $flow->assertStateAllowed(LoginStateEnum::COMPLETED);
        $this->addToAssertionCount(1);
    }

    public function testCompleteIsNotAllowedBeforeTwoFaWhenUserEnabled(): void
    {
        $flow = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(LoginStateEnum::PASSWORD, 'user-id'),
        );

        $this->expectException(InvalidLoginStateException::class);
        $flow->assertStateAllowed(LoginStateEnum::COMPLETED);
    }

    public function testGlobalTwoFaDisabledSkipsTwoFaWithoutLoadingUser(): void
    {
        $flow = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(LoginStateEnum::PASSWORD, 'user-id'),
            globalTwoFaEnabled: false,
            expectUserLookup: false,
        );

        $this->assertSame(LoginStateEnum::COMPLETED, $flow->resolveNextState());
        $flow->assertStateAllowed(LoginStateEnum::COMPLETED);
        $this->addToAssertionCount(1);
    }

    public function testMissingUserIdentifierDefaultsToRequiringTwoFaWithoutLoadingUser(): void
    {
        $flow = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(LoginStateEnum::PASSWORD, null),
            expectUserLookup: false,
        );

        $this->assertSame(LoginStateEnum::TWO_FACTOR_INITIATE, $flow->resolveNextState());
    }

    public function testMissingUserThrowsException(): void
    {
        $dispatcher = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(
                LoginStateEnum::PASSWORD,
                'user-id',
            ),
            twoFaEnabled: null,
        );

        $this->expectException(UserNotFoundException::class);

        $dispatcher->resolveNextState();
    }

    public function testCompletedFlowIsAccepted(): void
    {
        $flow = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(LoginStateEnum::COMPLETED, 'user-id'),
            expectUserLookup: false,
        );

        $flow->assertCompleted();
        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider uncompletedStateProvider
     */
    public function testUncompletedFlowIsRejected(?LoginStateEnum $loginState): void
    {
        $flow = $this->createAuthorizationFlow(
            $this->createAuthorizationSession($loginState, $loginState === null ? null : 'user-id'),
            expectUserLookup: false,
        );

        $this->expectException(InvalidLoginStateException::class);
        $flow->assertCompleted();
    }

    /** @return array<string, array{?LoginStateEnum}> */
    public static function uncompletedStateProvider(): array
    {
        return [
            'not started' => [null],
            'password' => [LoginStateEnum::PASSWORD],
            '2FA initiated' => [LoginStateEnum::TWO_FACTOR_INITIATE],
            '2FA completed' => [LoginStateEnum::TWO_FACTOR_COMPLETE],
        ];
    }

    public function testCompletedFlowWithoutUserIsRejected(): void
    {
        $flow = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(LoginStateEnum::COMPLETED, null),
            expectUserLookup: false,
        );

        $this->expectException(InvalidLoginStateException::class);
        $flow->assertCompleted();
    }

    private function createAuthorizationFlow(
        ?AuthorizationSession $authorizationSession,
        bool $globalTwoFaEnabled = true,
        ?bool $twoFaEnabled = true,
        bool $expectUserLookup = true,
    ): AuthorizationFlow {
        return new AuthorizationFlow(
            new AuthorizationFlowConfig(
                twoFactorEnabled: $globalTwoFaEnabled,
            ),
            $this->createUserConnector($twoFaEnabled, $expectUserLookup),
            $authorizationSession,
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

    private function createAuthorizationSession(?LoginStateEnum $loginState, ?string $userIdentifier): AuthorizationSession
    {
        $authorizationSession = new AuthorizationSession();
        $authorizationSession->setLoginState($loginState);
        $authorizationSession->setUserIdentifier($userIdentifier);

        return $authorizationSession;
    }
}
