<?php
declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\DataFixtures\AppFixtures;
use App\Tests\Helper\BearerAuthorizationTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

final class AuthorizationMetadataControllerTest extends WebTestCase
{
    use BearerAuthorizationTrait;

    public function testListsSupportedAudiences(): void
    {
        $client = self::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $client->request(
            'GET',
            $router->generate('authorization_audiences'),
            server: $this->authorizationHeader($client, AppFixtures::ACCESS_TOKEN_IDENTIFIER),
        );

        self::assertResponseIsSuccessful();
        $metadata = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('audiences', $metadata);
        self::assertContains('https://example.com/identity-link', $metadata['audiences']);
    }

    public function testReturnsScopesAndAliasesForAudience(): void
    {
        $client = self::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $client->request(
            'GET',
            $router->generate('authorization_scopes', [
                'audience' => 'https://example.com/identity-link',
            ]),
            server: $this->authorizationHeader($client, AppFixtures::ACCESS_TOKEN_IDENTIFIER),
        );

        self::assertResponseIsSuccessful();
        $metadata = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(
            ['description' => 'View users and their account details'],
            $metadata['scopes']['users.read'],
        );
        self::assertSame(
            'Request all Identity Link scopes available to the client',
            $metadata['aliases']['identity-link.all']['description'],
        );
        self::assertContains('users.read', $metadata['aliases']['identity-link.all']['scopes']);
    }

    public function testRequiresAudience(): void
    {
        $client = self::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $client->request(
            'GET',
            $router->generate('authorization_scopes'),
            server: $this->authorizationHeader($client, AppFixtures::ACCESS_TOKEN_IDENTIFIER),
        );

        self::assertResponseStatusCodeSame(400);
        self::assertSame(
            ['error' => 'The "audience" query parameter is required.'],
            json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR),
        );
    }

    public function testRejectsUnknownAudience(): void
    {
        $client = self::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $client->request(
            'GET',
            $router->generate('authorization_scopes', ['audience' => 'unknown']),
            server: $this->authorizationHeader($client, AppFixtures::ACCESS_TOKEN_IDENTIFIER),
        );

        self::assertResponseStatusCodeSame(404);
        self::assertSame(
            ['error' => 'Unknown audience "unknown".'],
            json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR),
        );
    }

    public function testRequiresAuthentication(): void
    {
        $client = self::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $client->request('GET', $router->generate('authorization_audiences'));

        self::assertResponseStatusCodeSame(401);
    }

}
