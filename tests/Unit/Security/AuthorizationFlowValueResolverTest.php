<?php
declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Api\Contract\ClientConnectorInterface;
use App\Api\Contract\ClientResponseInterface;
use App\Api\Contract\UserConnectorInterface;
use App\Entity\AuthorizationSession;
use App\Repository\AuthorizationSessionRepository;
use App\Security\Attribute\MapAuthorizationFlow;
use App\Security\AuthorizationFlow;
use App\Security\AuthorizationFlowConfig;
use App\Security\AuthorizationFlowFactory;
use App\Security\AuthorizationFlowValueResolver;
use App\Security\AuthorizationSessionBinding;
use App\Security\AuthorizationSessionResolver;
use App\Security\AuthorizationStateEnum;
use App\Security\Exception\BrowserMismatchAuthorizationSessionException;
use App\Security\Exception\ConsumedAuthorizationSessionException;
use App\Security\Exception\InvalidAuthorizationSessionException;
use App\Security\Exception\InvalidAuthorizationStateException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class AuthorizationFlowValueResolverTest extends TestCase
{
    private const SESSION_ID = '0b6f4a5e-6a43-4f0e-9a51-2f1f0c8a7d10';

    /** @var array<string, string> the binding cookie set for the session, as the browser would send it back */
    private array $cookies = [];

    public function testResolvesTheFlowOfTheSession(): void
    {
        $authorizationSession = $this->createAuthorizationSession();
        $resolver = $this->createResolver($authorizationSession);

        $result = [...$resolver->resolve($this->createRequest(), $this->createArgument(AuthorizationStateEnum::PASSWORD))];

        $this->assertCount(1, $result);
        $this->assertInstanceOf(AuthorizationFlow::class, $result[0]);
        $this->assertSame($authorizationSession, $result[0]->getAuthorizationSession());
    }

    public function testStepThatIsNotNextIsRejected(): void
    {
        $resolver = $this->createResolver($this->createAuthorizationSession());

        $this->expectException(InvalidAuthorizationStateException::class);
        [...$resolver->resolve($this->createRequest(), $this->createArgument(AuthorizationStateEnum::COMPLETED))];
    }

    public function testUnknownSessionIsRejected(): void
    {
        $resolver = $this->createResolver(null);

        $this->expectException(InvalidAuthorizationSessionException::class);
        [...$resolver->resolve($this->createRequest(), $this->createArgument(AuthorizationStateEnum::PASSWORD))];
    }

    public function testConsumedSessionIsRejected(): void
    {
        $authorizationSession = $this->createAuthorizationSession();
        $authorizationSession->consume();
        $resolver = $this->createResolver($authorizationSession);

        $this->expectException(ConsumedAuthorizationSessionException::class);
        [...$resolver->resolve($this->createRequest(), $this->createArgument(AuthorizationStateEnum::PASSWORD))];
    }

    public function testRequestWithoutTheBindingCookieIsRejected(): void
    {
        $resolver = $this->createResolver($this->createAuthorizationSession());
        $this->cookies = [];

        $this->expectException(BrowserMismatchAuthorizationSessionException::class);
        [...$resolver->resolve($this->createRequest(), $this->createArgument(AuthorizationStateEnum::PASSWORD))];
    }

    public function testRequestWithAnotherBindingCookieIsRejected(): void
    {
        $resolver = $this->createResolver($this->createAuthorizationSession());
        $this->cookies = array_map(static fn (): string => bin2hex(random_bytes(32)), $this->cookies);

        $this->expectException(BrowserMismatchAuthorizationSessionException::class);
        [...$resolver->resolve($this->createRequest(), $this->createArgument(AuthorizationStateEnum::PASSWORD))];
    }

    public function testArgumentWithoutTheAttributeIsIgnored(): void
    {
        $resolver = $this->createResolver($this->createAuthorizationSession());

        $argument = new ArgumentMetadata('authorizationFlow', AuthorizationFlow::class, false, false, null);

        $this->assertSame([], [...$resolver->resolve($this->createRequest(), $argument)]);
    }

    public function testAttributeOnArgumentOfAnotherTypeIsRejected(): void
    {
        $resolver = $this->createResolver($this->createAuthorizationSession());

        $argument = new ArgumentMetadata('authorizationSession', AuthorizationSession::class, false, false, null,
            attributes: [new MapAuthorizationFlow(AuthorizationStateEnum::PASSWORD)]);

        $this->expectException(\LogicException::class);
        [...$resolver->resolve($this->createRequest(), $argument)];
    }

    public function testRouteWithoutIdIsRejected(): void
    {
        $resolver = $this->createResolver($this->createAuthorizationSession());

        $this->expectException(\LogicException::class);
        [...$resolver->resolve(new Request(cookies: $this->cookies), $this->createArgument(AuthorizationStateEnum::PASSWORD))];
    }

    private function createResolver(?AuthorizationSession $authorizationSession): AuthorizationFlowValueResolver
    {
        $repository = $this->createMock(AuthorizationSessionRepository::class);
        $repository->method('find')->with(self::SESSION_ID)->willReturn($authorizationSession);

        $client = $this->createMock(ClientResponseInterface::class);
        $client->method('isConsentRequired')->willReturn(false);
        $clientConnector = $this->createMock(ClientConnectorInterface::class);
        $clientConnector->method('getClientById')->willReturn($client);

        return new AuthorizationFlowValueResolver(
            new AuthorizationSessionResolver($repository),
            new AuthorizationFlowFactory(
                new AuthorizationFlowConfig(twoFactorEnabled: false),
                $this->createMock(UserConnectorInterface::class),
                $clientConnector,
            ),
            new AuthorizationSessionBinding(),
            new NullLogger(),
        );
    }

    private function createRequest(): Request
    {
        return new Request(attributes: ['id' => self::SESSION_ID], cookies: $this->cookies);
    }

    private function createArgument(AuthorizationStateEnum $state): ArgumentMetadata
    {
        return new ArgumentMetadata('authorizationFlow', AuthorizationFlow::class, false, false, null,
            attributes: [new MapAuthorizationFlow($state)]);
    }

    private function createAuthorizationSession(): AuthorizationSession
    {
        $authorizationSession = new AuthorizationSession();
        $authorizationSession->setClientId('client-id');

        // the id is generated by Doctrine on persist
        (new \ReflectionProperty(AuthorizationSession::class, 'id'))->setValue($authorizationSession, self::SESSION_ID);

        $response = new Response();
        (new AuthorizationSessionBinding())->bind($authorizationSession, $response);
        foreach ($response->headers->getCookies() as $cookie) {
            $this->cookies[$cookie->getName()] = (string) $cookie->getValue();
        }

        return $authorizationSession;
    }
}
