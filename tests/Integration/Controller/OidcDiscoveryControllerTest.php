<?php
declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Security\Jwt\JwtConfig;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

final class OidcDiscoveryControllerTest extends WebTestCase
{
    public function testDiscoveryDocumentPublishesConfiguredIssuerAndValidJwksEndpoint(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();
        $router = $container->get(RouterInterface::class);
        $jwtConfig = $container->get(JwtConfig::class);

        $client->request('GET', $router->generate('oidc_discovery'));

        $discoveryResponse = $client->getResponse();
        $this->assertResponseIsSuccessful();
        $this->assertJson($discoveryResponse->getContent());

        $document = json_decode($discoveryResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame($jwtConfig->getIssuer(), $document['issuer']);
        $this->assertSame(
            $router->generate('oidc_jwks', [], RouterInterface::ABSOLUTE_URL),
            $document['jwks_uri'],
        );

        $client->request('GET', $document['jwks_uri']);

        $jwksResponse = $client->getResponse();
        $this->assertResponseIsSuccessful();
        $this->assertJson($jwksResponse->getContent());

        $jwks = json_decode($jwksResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertCount(1, $jwks['keys']);
        $this->assertSame('RSA', $jwks['keys'][0]['kty']);
        $this->assertSame('RS256', $jwks['keys'][0]['alg']);
        $this->assertSame('sig', $jwks['keys'][0]['use']);
        $this->assertSame($jwtConfig->getKid(), $jwks['keys'][0]['kid']);
        $this->assertNotEmpty($jwks['keys'][0]['n']);
        $this->assertNotEmpty($jwks['keys'][0]['e']);
    }
}
