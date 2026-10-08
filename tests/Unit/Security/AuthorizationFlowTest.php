<?php
declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Api\Contract\UserConnectorInterface;
use App\Api\Contract\UserResponseInterface;
use App\Entity\AuthorizationSession;
use App\Repository\AuthorizationSessionRepository;
use App\Security\Exception\InvalidAuthorizationStateException;
use App\Security\Exception\UserNotFoundException;
use App\Security\AuthorizationFlow;
use App\Security\AuthorizationFlowConfig;
use App\Security\AuthorizationStateEnum;
use PHPUnit\Framework\TestCase;

class AuthorizationFlowTest extends TestCase
{
    public function testInitialStateStartsWithPassword(): void
    {
        $flow = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(null, null),
            expectUserLookup: false,
        );

        $this->assertSame(AuthorizationStateEnum::PASSWORD, $flow->resolveNextState());

        $flow->assertStateAllowed(AuthorizationStateEnum::PASSWORD);
        $this->addToAssertionCount(1);
    }

    public function testNextStateRequiresTwoFaWhenGloballyAndUserEnabled(): void
    {
        $dispatcher = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(AuthorizationStateEnum::PASSWORD, 'user-id'),
        );

        $this->assertSame(AuthorizationStateEnum::TWO_FACTOR_INITIATE, $dispatcher->resolveNextState());
    }

    public function testTwoFaFlowProgressesInOrderWhenUserEnabled(): void
    {
        $flow = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(AuthorizationStateEnum::TWO_FACTOR_INITIATE, 'user-id'),
        );

        $this->assertSame(AuthorizationStateEnum::TWO_FACTOR_COMPLETE, $flow->resolveNextState());
        $flow->assertStateAllowed(AuthorizationStateEnum::TWO_FACTOR_COMPLETE);
        $this->addToAssertionCount(1);
    }

    public function testCompleteIsAllowedAfterTwoFaCompletes(): void
    {
        $flow = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(AuthorizationStateEnum::TWO_FACTOR_COMPLETE, 'user-id'),
        );

        $this->assertSame(AuthorizationStateEnum::COMPLETED, $flow->resolveNextState());
        $flow->assertStateAllowed(AuthorizationStateEnum::COMPLETED);
        $this->addToAssertionCount(1);
    }

    public function testNextStateSkipsTwoFaWhenUserDisabled(): void
    {
        $flow = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(AuthorizationStateEnum::PASSWORD, 'user-id'),
            twoFaEnabled: false,
        );

        $this->assertSame(AuthorizationStateEnum::COMPLETED, $flow->resolveNextState());
    }

    public function testTwoFaRoutesAreNotAllowedWhenUserDisabled(): void
    {
        $flow = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(AuthorizationStateEnum::PASSWORD, 'user-id'),
            twoFaEnabled: false,
        );

        $flow->assertStateAllowed(AuthorizationStateEnum::COMPLETED);
        $this->addToAssertionCount(1);
    }

    public function testCompleteIsNotAllowedBeforeTwoFaWhenUserEnabled(): void
    {
        $flow = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(AuthorizationStateEnum::PASSWORD, 'user-id'),
        );

        $this->expectException(InvalidAuthorizationStateException::class);
        $flow->assertStateAllowed(AuthorizationStateEnum::COMPLETED);
    }

    public function testGlobalTwoFaDisabledSkipsTwoFaWithoutLoadingUser(): void
    {
        $flow = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(AuthorizationStateEnum::PASSWORD, 'user-id'),
            globalTwoFaEnabled: false,
            expectUserLookup: false,
        );

        $this->assertSame(AuthorizationStateEnum::COMPLETED, $flow->resolveNextState());
        $flow->assertStateAllowed(AuthorizationStateEnum::COMPLETED);
        $this->addToAssertionCount(1);
    }

    public function testMissingUserIdentifierDefaultsToRequiringTwoFaWithoutLoadingUser(): void
    {
        $flow = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(AuthorizationStateEnum::PASSWORD, null),
            expectUserLookup: false,
        );

        $this->assertSame(AuthorizationStateEnum::TWO_FACTOR_INITIATE, $flow->resolveNextState());
    }

    public function testMissingUserThrowsException(): void
    {
        $dispatcher = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(
                AuthorizationStateEnum::PASSWORD,
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
            $this->createAuthorizationSession(AuthorizationStateEnum::COMPLETED, 'user-id'),
            expectUserLookup: false,
        );

        $flow->assertCompleted();
        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider uncompletedStateProvider
     */
    public function testUncompletedFlowIsRejected(?AuthorizationStateEnum $authorizationState): void
    {
        $flow = $this->createAuthorizationFlow(
            $this->createAuthorizationSession($authorizationState, $authorizationState === null ? null : 'user-id'),
            expectUserLookup: false,
        );

        $this->expectException(InvalidAuthorizationStateException::class);
        $flow->assertCompleted();
    }

    /** @return array<string, array{?AuthorizationStateEnum}> */
    public static function uncompletedStateProvider(): array
    {
        return [
            'not started' => [null],
            'password' => [AuthorizationStateEnum::PASSWORD],
            '2FA initiated' => [AuthorizationStateEnum::TWO_FACTOR_INITIATE],
            '2FA completed' => [AuthorizationStateEnum::TWO_FACTOR_COMPLETE],
        ];
    }

    public function testCompletedFlowWithoutUserIsRejected(): void
    {
        $flow = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(AuthorizationStateEnum::COMPLETED, null),
            expectUserLookup: false,
        );

        $this->expectException(InvalidAuthorizationStateException::class);
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

    private function createAuthorizationSession(?AuthorizationStateEnum $authorizationState, ?string $userIdentifier): AuthorizationSession
    {
        $authorizationSession = new AuthorizationSession();
        $authorizationSession->setAuthorizationState($authorizationState);
        $authorizationSession->setUserIdentifier($userIdentifier);

        return $authorizationSession;
    }
}
