<?php
declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\DataFixtures\AppFixtures;
use App\Repository\AccessTokenRepository;
use App\Tests\Helper\BearerAuthorizationTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

final class OidcUserInfoControllerTest extends WebTestCase
{
    use BearerAuthorizationTrait;

    public function testReturnsOnlyTheSubjectForAccessTokenWithOnlyTheOpenIdScope(): void
    {
        $client = self::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $client->request(
            'GET',
            $router->generate('oidc_user_info'),
            server: $this->authorizationHeader($client, AppFixtures::ACCESS_TOKEN_IDENTIFIER),
        );

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['sub' => AppFixtures::USER_IDENTIFIER],
            json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR),
        );
    }

    public function testRejectsAccessTokenWithoutTheOpenIdScope(): void
    {
        $client = self::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);

        // An access token for an API rather than from an OpenID Connect request.
        $accessToken = $client->getContainer()->get(AccessTokenRepository::class)->findOneBy([
            'identifier' => AppFixtures::ACCESS_TOKEN_IDENTIFIER,
        ]);
        $accessToken->setScopes(json_encode(['users.read'], JSON_THROW_ON_ERROR));
        $entityManager->flush();

        $client->request(
            'GET',
            $router->generate('oidc_user_info'),
            server: $this->authorizationHeader($client, AppFixtures::ACCESS_TOKEN_IDENTIFIER),
        );

        self::assertResponseStatusCodeSame(403);
        self::assertSame(
            [
                'error' => 'insufficient_scope',
                'error_description' => 'The access token must have the openid scope.',
            ],
            json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR),
        );
        self::assertSame(
            'Bearer realm="user-info", error="insufficient_scope", error_description="The access token must have the openid scope.", scope="openid"',
            $client->getResponse()->headers->get('WWW-Authenticate'),
        );
    }
}
