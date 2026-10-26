<?php
declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\DataFixtures\AppFixtures;
use App\Repository\AccessTokenRepository;
use App\Repository\RefreshTokenRepository;
use App\Service\JwtTokenGenerator;
use App\Tests\Helper\TestHelper;
use Doctrine\ORM\EntityManagerInterface;
use Firebase\JWT\JWT;
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

    public function testAccessTokenIsActiveRightAfterIssuance(): void
    {
        $client = self::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $client->request('POST', $router->generate('oauth2_token'), [
            'client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
            'client_secret' => AppFixtures::PRIVATE_CLIENT_SECRET,
            'grant_type' => 'client_credentials',
        ]);
        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $token = json_decode($client->getResponse()->getContent(), true)['access_token'];

        // Introspected right away: the token's nbf is its issue time, including microseconds.
        $auth = 'Basic ' . base64_encode(AppFixtures::PRIVATE_CLIENT_IDENTIFIER . ':' . AppFixtures::PRIVATE_CLIENT_SECRET);
        $client->request('POST', $router->generate('oauth2_token_introspect'), [
            'token' => $token,
        ], [], ['HTTP_Authorization' => $auth]);

        $response = $client->getResponse();
        $json = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($json['active']);
    }

    public function testAccessTokenIsInactiveBeforeItsNotBeforeTime(): void
    {
        $client = self::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);
        $jwtTokenGenerator = $client->getContainer()->get(JwtTokenGenerator::class);

        $token = $jwtTokenGenerator->createTokenByPayload([
            'jti' => AppFixtures::ACCESS_TOKEN_IDENTIFIER,
            'nbf' => time() + 60,
            'exp' => time() + 3600,
        ]);

        $auth = 'Basic ' . base64_encode(AppFixtures::PRIVATE_CLIENT_IDENTIFIER . ':' . AppFixtures::PRIVATE_CLIENT_SECRET);
        $client->request('POST', $router->generate('oauth2_token_introspect'), [
            'token' => $token,
        ], [], ['HTTP_Authorization' => $auth]);

        $response = $client->getResponse();
        $json = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['active' => false], $json);
    }

    public function testJwtWithoutJtiIsInactive(): void
    {
        $client = self::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);
        $jwtTokenGenerator = $client->getContainer()->get(JwtTokenGenerator::class);

        // Signed with our key but not issued by the authorization server, like JwtTokenGenerator's internal tokens.
        $token = $jwtTokenGenerator->createTokenByPayload([
            'sub' => AppFixtures::USER_IDENTIFIER,
            'exp' => time() + 3600,
        ]);

        $auth = 'Basic ' . base64_encode(AppFixtures::PRIVATE_CLIENT_IDENTIFIER . ':' . AppFixtures::PRIVATE_CLIENT_SECRET);
        $client->request('POST', $router->generate('oauth2_token_introspect'), [
            'token' => $token,
        ], [], ['HTTP_Authorization' => $auth]);

        $response = $client->getResponse();
        $json = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['active' => false], $json);
    }

    public function testJwtWithNonStringJtiIsInactive(): void
    {
        $client = self::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);
        $jwtTokenGenerator = $client->getContainer()->get(JwtTokenGenerator::class);

        $token = $jwtTokenGenerator->createTokenByPayload([
            'jti' => 123,
            'exp' => time() + 3600,
        ]);

        $auth = 'Basic ' . base64_encode(AppFixtures::PRIVATE_CLIENT_IDENTIFIER . ':' . AppFixtures::PRIVATE_CLIENT_SECRET);
        $client->request('POST', $router->generate('oauth2_token_introspect'), [
            'token' => $token,
        ], [], ['HTTP_Authorization' => $auth]);

        $response = $client->getResponse();
        $json = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['active' => false], $json);
    }

    public function testExpiredAccessTokenIsInactiveBeforeItsNotBeforeTime(): void
    {
        $client = self::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);
        $jwtTokenGenerator = $client->getContainer()->get(JwtTokenGenerator::class);

        // TokenValidator passes expired JWTs on, and the stored token is still valid, so only the nbf check of
        // JWT::decode(), which runs before its exp check, keeps this token inactive.
        $token = $jwtTokenGenerator->createTokenByPayload([
            'jti' => AppFixtures::ACCESS_TOKEN_IDENTIFIER,
            'nbf' => time() + 60,
            'exp' => time() - 60,
        ]);

        $auth = 'Basic ' . base64_encode(AppFixtures::PRIVATE_CLIENT_IDENTIFIER . ':' . AppFixtures::PRIVATE_CLIENT_SECRET);
        $client->request('POST', $router->generate('oauth2_token_introspect'), [
            'token' => $token,
        ], [], ['HTTP_Authorization' => $auth]);

        $response = $client->getResponse();
        $json = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['active' => false], $json);
    }

    public function testAccessTokenSignedWithAnotherKeyIsInactive(): void
    {
        $client = self::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);
        $jwtTokenGenerator = $client->getContainer()->get(JwtTokenGenerator::class);

        // The jti of an active token is no secret; the signature is what makes the token ours.
        $claims = [
            'jti' => AppFixtures::ACCESS_TOKEN_IDENTIFIER,
            'nbf' => time(),
            'exp' => time() + 3600,
        ];
        openssl_pkey_export(openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA]), $foreignPrivateKey);

        $auth = 'Basic ' . base64_encode(AppFixtures::PRIVATE_CLIENT_IDENTIFIER . ':' . AppFixtures::PRIVATE_CLIENT_SECRET);
        $client->request('POST', $router->generate('oauth2_token_introspect'), [
            'token' => $jwtTokenGenerator->createTokenByPayload($claims),
        ], [], ['HTTP_Authorization' => $auth]);
        $this->assertTrue(json_decode($client->getResponse()->getContent(), true)['active']);

        $client->request('POST', $router->generate('oauth2_token_introspect'), [
            'token' => JWT::encode($claims, $foreignPrivateKey, 'RS256'),
        ], [], ['HTTP_Authorization' => $auth]);

        $response = $client->getResponse();
        $json = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['active' => false], $json);
    }

    public function testRevokedAccessTokenIsInactive(): void
    {
        $client = self::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);
        $testHelper = $client->getContainer()->get(TestHelper::class);
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        $accessTokenRepository = $client->getContainer()->get(AccessTokenRepository::class);

        list($accessToken) = $accessTokenRepository->findBy(['identifier' => AppFixtures::ACCESS_TOKEN_IDENTIFIER]);
        $accessToken->setIsRevoked(true);
        $entityManager->flush();
        $token = $testHelper->generateJwtPayloadByAccessToken($accessToken);

        $auth = 'Basic ' . base64_encode(AppFixtures::PRIVATE_CLIENT_IDENTIFIER . ':' . AppFixtures::PRIVATE_CLIENT_SECRET);
        $client->request('POST', $router->generate('oauth2_token_introspect'), [
            'token' => $token,
        ], [], ['HTTP_Authorization' => $auth]);

        $response = $client->getResponse();
        $json = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['active' => false], $json);
    }

    public function testAccessTokenOfAnotherClientIsInactive(): void
    {
        $client = self::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);
        $testHelper = $client->getContainer()->get(TestHelper::class);
        $accessTokenRepository = $client->getContainer()->get(AccessTokenRepository::class);

        list($accessToken) = $accessTokenRepository->findBy(['identifier' => AppFixtures::ACCESS_TOKEN_IDENTIFIER]);
        $token = $testHelper->generateJwtPayloadByAccessToken($accessToken);

        // The token belongs to the private client.
        $auth = 'Basic ' . base64_encode(
            AppFixtures::MULTI_REDIRECT_CLIENT_IDENTIFIER . ':' . AppFixtures::MULTI_REDIRECT_CLIENT_SECRET,
        );
        $client->request('POST', $router->generate('oauth2_token_introspect'), [
            'token' => $token,
        ], [], ['HTTP_Authorization' => $auth]);

        $response = $client->getResponse();
        $json = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['active' => false], $json);
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