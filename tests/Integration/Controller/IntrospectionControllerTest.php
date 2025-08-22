<?php
declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\DataFixtures\AppFixtures;
use App\Repository\AccessTokenRepository;
use App\Repository\RefreshTokenRepository;
use App\Tests\Helper\TestHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

class IntrospectionControllerTest extends WebTestCase
{
    public function testMissingTokenParameter(): void
    {
        $client = self::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $auth = 'Basic ' . base64_encode(AppFixtures::PRIVATE_CLIENT_IDENTIFIER . ':' . AppFixtures::PRIVATE_CLIENT_SECRET);
        $client->request('POST', $router->generate('oauth2_token_introspect'), [
            'token_type_hint' => 'access_token',
        ], [], ['HTTP_Authorization' => $auth]);

        $response = $client->getResponse();
        $json = json_decode($response->getContent(), true);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('invalid_request', $json['error']);
    }

    public function testInvalidClientCredentials(): void
    {
        $client = self::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $auth = 'Basic ' . base64_encode('invalid_client_id:invalid_client_secret');
        $client->request('POST', $router->generate('oauth2_token_introspect'), [
            'token' => AppFixtures::ACCESS_TOKEN_IDENTIFIER,
            'token_type_hint' => 'access_token',
        ], [], ['HTTP_Authorization' => $auth]);;

        $response = $client->getResponse();
        $json = json_decode($response->getContent(), true);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('invalid_client', $json['error']);
    }

    public function testActiveAccessToken(): void
    {
        $client = self::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);
        $testHelper = $client->getContainer()->get(TestHelper::class);
        $accessTokenRepository = $client->getContainer()->get(AccessTokenRepository::class);

        list($accessToken) = $accessTokenRepository->findBy(['identifier' => AppFixtures::ACCESS_TOKEN_IDENTIFIER]);
        $token = $testHelper->generateJwtPayloadByAccessToken($accessToken);

        $auth = 'Basic ' . base64_encode(AppFixtures::PRIVATE_CLIENT_IDENTIFIER . ':' . AppFixtures::PRIVATE_CLIENT_SECRET);
        $client->request('POST', $router->generate('oauth2_token_introspect'), [
            'token' => $token,
            'token_type_hint' => 'access_token',
        ], [], ['HTTP_Authorization' => $auth]);;

        $response = $client->getResponse();

        $json = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($json['active']);
        $this->assertSame(AppFixtures::PRIVATE_CLIENT_IDENTIFIER, $json['client_id']);
    }

    public function testExpiredAccessToken(): void
    {
        $client = self::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);
        $testHelper = $client->getContainer()->get(TestHelper::class);
        $accessTokenRepository = $client->getContainer()->get(AccessTokenRepository::class);

        list($accessToken) = $accessTokenRepository->findBy(['identifier' => AppFixtures::EXPIRED_ACCESS_TOKEN_IDENTIFIER]);
        $token = $testHelper->generateJwtPayloadByAccessToken($accessToken);

        $auth = 'Basic ' . base64_encode(AppFixtures::PRIVATE_CLIENT_IDENTIFIER . ':' . AppFixtures::PRIVATE_CLIENT_SECRET);
        $client->request('POST', $router->generate('oauth2_token_introspect'), [
            'token' => $token,
            'token_type_hint' => 'access_token',
        ], [], ['HTTP_Authorization' => $auth]);;

        $response = $client->getResponse();
        $json = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($json['active']);
    }

    public function testRefreshTokenIntrospection(): void
    {
        $client = self::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);
        $testHelper = $client->getContainer()->get(TestHelper::class);
        $refreshToken = $client->getContainer()->get(RefreshTokenRepository::class);

        list($refreshToken) = $refreshToken->findBy(['identifier' => AppFixtures::REFRESH_TOKEN_IDENTIFIER]);
        $token = $testHelper->generateEncryptedRefreshTokenPayload($refreshToken);

        $auth = 'Basic ' . base64_encode(AppFixtures::PRIVATE_CLIENT_IDENTIFIER . ':' . AppFixtures::PRIVATE_CLIENT_SECRET);
        $client->request('POST', $router->generate('oauth2_token_introspect'), [
            'token' => $token,
            'token_type_hint' => 'refresh_token',
        ], [], ['HTTP_Authorization' => $auth]);;

        $response = $client->getResponse();
        $json = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($json['active']);
        $this->assertSame(AppFixtures::USER_IDENTIFIER, $json['sub']);

        // test without passing token_type_hint
        $client->request('POST', $router->generate('oauth2_token_introspect'), [
            'token' => $token,
        ], [], ['HTTP_Authorization' => $auth]);;

        $response = $client->getResponse();
        $json = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($json['active']);
    }
}