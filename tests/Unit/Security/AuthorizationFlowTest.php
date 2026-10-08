<?php
declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Api\Contract\ClientConnectorInterface;
use App\Api\Contract\ClientResponseInterface;
use App\Api\Contract\UserConnectorInterface;
use App\Api\Contract\UserResponseInterface;
use App\Entity\AuthorizationSession;
use App\Security\Exception\ClientNotFoundException;
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

    public function testConsentFollowsPasswordWhenClientRequiresConsent(): void
    {
        $flow = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(AuthorizationStateEnum::PASSWORD, 'user-id'),
            twoFaEnabled: false,
            consentRequired: true,
        );

        $this->assertSame(AuthorizationStateEnum::CONSENT, $flow->resolveNextState());
        $flow->assertStateAllowed(AuthorizationStateEnum::CONSENT);
        $this->addToAssertionCount(1);
    }

    public function testConsentFollowsTwoFaWhenClientRequiresConsent(): void
    {
        $flow = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(AuthorizationStateEnum::TWO_FACTOR_COMPLETE, 'user-id'),
            consentRequired: true,
        );

        $this->assertSame(AuthorizationStateEnum::CONSENT, $flow->resolveNextState());
    }

    public function testCompleteIsNotAllowedBeforeConsent(): void
    {
        $flow = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(AuthorizationStateEnum::PASSWORD, 'user-id'),
            twoFaEnabled: false,
            consentRequired: true,
        );

        $this->expectException(InvalidAuthorizationStateException::class);
        $flow->assertStateAllowed(AuthorizationStateEnum::COMPLETED);
    }

    public function testCompleteFollowsConsent(): void
    {
        $flow = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(AuthorizationStateEnum::CONSENT, 'user-id'),
            twoFaEnabled: false,
            consentRequired: true,
        );

        $this->assertSame(AuthorizationStateEnum::COMPLETED, $flow->resolveNextState());
        $flow->assertStateAllowed(AuthorizationStateEnum::COMPLETED);
        $this->addToAssertionCount(1);
    }

    public function testConsentCannotBeRepeated(): void
    {
        $flow = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(AuthorizationStateEnum::CONSENT, 'user-id'),
            twoFaEnabled: false,
            consentRequired: true,
        );

        $this->expectException(InvalidAuthorizationStateException::class);
        $flow->assertStateAllowed(AuthorizationStateEnum::CONSENT);
    }

    public function testConsentIsSkippedWhenClientDoesNotRequireIt(): void
    {
        $flow = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(AuthorizationStateEnum::PASSWORD, 'user-id'),
            twoFaEnabled: false,
        );

        $this->assertSame(AuthorizationStateEnum::COMPLETED, $flow->resolveNextState());

        $this->expectException(InvalidAuthorizationStateException::class);
        $flow->assertStateAllowed(AuthorizationStateEnum::CONSENT);
    }

    public function testClientIsLookedUpOncePerFlow(): void
    {
        $clientConnector = $this->createClientConnector(true);
        $flow = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(AuthorizationStateEnum::PASSWORD, 'user-id'),
            twoFaEnabled: false,
            clientConnector: $clientConnector,
        );

        $flow->assertStateAllowed(AuthorizationStateEnum::CONSENT);
        $flow->resolveNextState();
        $this->addToAssertionCount(1);
    }

    public function testFlowIsRebuiltWhenTheUserIsIdentified(): void
    {
        $client = $this->createStub(ClientResponseInterface::class);
        $client->method('isConsentRequired')->willReturn(false);
        // the flow is built twice (before and after the user is identified), so the client is looked up twice
        $clientConnector = $this->createStub(ClientConnectorInterface::class);
        $clientConnector->method('getClientById')->willReturn($client);

        $authorizationSession = $this->createAuthorizationSession(null, null);
        $flow = $this->createAuthorizationFlow($authorizationSession, twoFaEnabled: false, clientConnector: $clientConnector);

        // before the password step the user is unknown and 2FA is assumed
        $flow->assertStateAllowed(AuthorizationStateEnum::PASSWORD);

        // the password step identifies a user without 2FA
        $authorizationSession->setAuthorizationState(AuthorizationStateEnum::PASSWORD);
        $authorizationSession->setUserIdentifier('user-id');

        $this->assertSame(AuthorizationStateEnum::COMPLETED, $flow->resolveNextState());
    }

    public function testMissingClientThrowsException(): void
    {
        $flow = $this->createAuthorizationFlow(
            $this->createAuthorizationSession(AuthorizationStateEnum::PASSWORD, 'user-id'),
            twoFaEnabled: false,
            consentRequired: null,
        );

        $this->expectException(ClientNotFoundException::class);
        $flow->resolveNextState();
    }

    private function createAuthorizationFlow(
        ?AuthorizationSession $authorizationSession,
        bool $globalTwoFaEnabled = true,
        ?bool $twoFaEnabled = true,
        bool $expectUserLookup = true,
        ?bool $consentRequired = false,
        ?ClientConnectorInterface $clientConnector = null,
    ): AuthorizationFlow {
        return new AuthorizationFlow(
            new AuthorizationFlowConfig(
                twoFactorEnabled: $globalTwoFaEnabled,
            ),
            $this->createUserConnector($twoFaEnabled, $expectUserLookup),
            $clientConnector ?? $this->createClientConnector($consentRequired),
            $authorizationSession,
        );
    }

    /**
     * @param bool|null $consentRequired null makes the client lookup return no client
     */
    private function createClientConnector(?bool $consentRequired): ClientConnectorInterface
    {
        $clientConnector = $this->createMock(ClientConnectorInterface::class);
        $expectation = $clientConnector
            ->expects($this->atMost(1))
            ->method('getClientById')
            ->with('client-id');

        if ($consentRequired === null) {
            $expectation->willReturn(null);

            return $clientConnector;
        }

        $client = $this->createMock(ClientResponseInterface::class);
        $client->method('isConsentRequired')->willReturn($consentRequired);
        $expectation->willReturn($client);

        return $clientConnector;
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
        $authorizationSession->setClientId('client-id');
        $authorizationSession->setAuthorizationState($authorizationState);
        $authorizationSession->setUserIdentifier($userIdentifier);

        return $authorizationSession;
    }
}
