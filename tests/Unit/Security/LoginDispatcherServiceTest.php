<?php
declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Api\Contract\UserConnectorInterface;
use App\Api\Contract\UserResponseInterface;
use App\Entity\AuthRequest;
use App\Repository\AuthRequestRepository;
use App\Security\LoginDispatcherService;
use App\Security\LoginStateEnum;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class LoginDispatcherServiceTest extends TestCase
{
    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $request = new Request();
        $request->attributes->set('id', 'auth-request-id');

        $this->requestStack = new RequestStack();
        $this->requestStack->push($request);
    }

    public function testInitialStateStartsWithPassword(): void
    {
        $dispatcher = $this->createDispatcher(
            $this->createAuthRequest(null, null),
            expectUserLookup: false,
        );

        $this->assertSame(LoginStateEnum::PASSWORD, $dispatcher->getNextState());
        $this->assertTrue($dispatcher->isStateAllowed(LoginStateEnum::PASSWORD->value));
        $this->assertFalse($dispatcher->isStateAllowed(LoginStateEnum::TWO_FACTOR_INITIATE->value));
    }

    public function testNextStateRequiresTwoFaWhenGloballyAndUserEnabled(): void
    {
        $dispatcher = $this->createDispatcher(
            $this->createAuthRequest(LoginStateEnum::PASSWORD, 'user-id'),
            twoFaEnabled: true,
        );

        $this->assertSame(LoginStateEnum::TWO_FACTOR_INITIATE, $dispatcher->getNextState());
    }

    public function testTwoFaFlowProgressesInOrderWhenUserEnabled(): void
    {
        $dispatcher = $this->createDispatcher(
            $this->createAuthRequest(LoginStateEnum::TWO_FACTOR_INITIATE, 'user-id'),
            twoFaEnabled: true,
        );

        $this->assertSame(LoginStateEnum::TWO_FACTOR_COMPLETE, $dispatcher->getNextState());
        $this->assertTrue($dispatcher->isStateAllowed(LoginStateEnum::TWO_FACTOR_COMPLETE->value));
        $this->assertFalse($dispatcher->isStateAllowed(LoginStateEnum::COMPLETED->value));
    }

    public function testCompleteIsAllowedAfterTwoFaCompletes(): void
    {
        $dispatcher = $this->createDispatcher(
            $this->createAuthRequest(LoginStateEnum::TWO_FACTOR_COMPLETE, 'user-id'),
            twoFaEnabled: true,
        );

        $this->assertSame(LoginStateEnum::COMPLETED, $dispatcher->getNextState());
        $this->assertTrue($dispatcher->isStateAllowed(LoginStateEnum::COMPLETED->value));
    }

    public function testNextStateSkipsTwoFaWhenUserDisabled(): void
    {
        $dispatcher = $this->createDispatcher(
            $this->createAuthRequest(LoginStateEnum::PASSWORD, 'user-id'),
            twoFaEnabled: false,
        );

        $this->assertSame(LoginStateEnum::COMPLETED, $dispatcher->getNextState());
    }

    public function testTwoFaRoutesAreNotAllowedWhenUserDisabled(): void
    {
        $dispatcher = $this->createDispatcher(
            $this->createAuthRequest(LoginStateEnum::PASSWORD, 'user-id'),
            twoFaEnabled: false,
        );

        $this->assertFalse($dispatcher->isStateAllowed(LoginStateEnum::TWO_FACTOR_INITIATE->value));
        $this->assertTrue($dispatcher->isStateAllowed(LoginStateEnum::COMPLETED->value));
    }

    public function testCompleteIsNotAllowedBeforeTwoFaWhenUserEnabled(): void
    {
        $dispatcher = $this->createDispatcher(
            $this->createAuthRequest(LoginStateEnum::PASSWORD, 'user-id'),
            twoFaEnabled: true,
        );

        $this->assertFalse($dispatcher->isStateAllowed(LoginStateEnum::COMPLETED->value));
        $this->assertFalse($dispatcher->isStateAllowed(LoginStateEnum::TWO_FACTOR_COMPLETE->value));
    }

    public function testGlobalTwoFaDisabledSkipsTwoFaWithoutLoadingUser(): void
    {
        $dispatcher = $this->createDispatcher(
            $this->createAuthRequest(LoginStateEnum::PASSWORD, 'user-id'),
            globalTwoFaEnabled: false,
            expectUserLookup: false,
        );

        $this->assertSame(LoginStateEnum::COMPLETED, $dispatcher->getNextState());
        $this->assertTrue($dispatcher->isStateAllowed(LoginStateEnum::COMPLETED->value));
        $this->assertFalse($dispatcher->isStateAllowed(LoginStateEnum::TWO_FACTOR_INITIATE->value));
    }

    public function testMissingUserDefaultsToRequiringTwoFa(): void
    {
        $dispatcher = $this->createDispatcher(
            $this->createAuthRequest(LoginStateEnum::PASSWORD, 'user-id'),
            twoFaEnabled: null,
        );

        $this->assertSame(LoginStateEnum::TWO_FACTOR_INITIATE, $dispatcher->getNextState());
    }

    public function testMissingUserIdentifierDefaultsToRequiringTwoFaWithoutLoadingUser(): void
    {
        $dispatcher = $this->createDispatcher(
            $this->createAuthRequest(LoginStateEnum::PASSWORD, null),
            expectUserLookup: false,
        );

        $this->assertSame(LoginStateEnum::TWO_FACTOR_INITIATE, $dispatcher->getNextState());
    }

    public function testMissingAuthRequestReturnsNoNextStateAndDisallowsState(): void
    {
        $dispatcher = $this->createDispatcher(
            null,
            expectUserLookup: false,
        );

        $this->assertNull($dispatcher->getNextState());
        $this->assertFalse($dispatcher->isStateAllowed(LoginStateEnum::PASSWORD->value));
    }

    public function testMissingTwoFaConfigThrowsException(): void
    {
        $dispatcher = new LoginDispatcherService(
            $this->createAuthRequestRepository($this->createAuthRequest(null, null)),
            $this->requestStack,
            $this->createUserConnector(expectUserLookup: false),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('login_two_fa_enabled is not set in config');

        $dispatcher->getNextState();
    }

    private function createDispatcher(
        ?AuthRequest $authRequest,
        bool $globalTwoFaEnabled = true,
        ?bool $twoFaEnabled = true,
        bool $expectUserLookup = true,
    ): LoginDispatcherService {
        $dispatcher = new LoginDispatcherService(
            $this->createAuthRequestRepository($authRequest),
            $this->requestStack,
            $this->createUserConnector($twoFaEnabled, $expectUserLookup),
        );
        $dispatcher->setConfig(['login_two_fa_enabled' => $globalTwoFaEnabled]);

        return $dispatcher;
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

    private function createAuthRequestRepository(?AuthRequest $authRequest): AuthRequestRepository
    {
        $repository = $this->createMock(AuthRequestRepository::class);
        $repository
            ->method('findActive')
            ->with('auth-request-id')
            ->willReturn($authRequest);

        return $repository;
    }
}
