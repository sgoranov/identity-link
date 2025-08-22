<?php
declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\DataFixtures\AppFixtures;
use App\Repository\AccessTokenRepository;
use App\Repository\RefreshTokenRepository;
use App\Tests\Helper\TestHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

class RevocationControllerTest extends WebTestCase
{
    public function testMissingTokenParameterReturnsInvalidRequest(): void
    {
        $client = self::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $auth = 'Basic ' . base64_encode(AppFixtures::PRIVATE_CLIENT_IDENTIFIER . ':' . AppFixtures::PRIVATE_CLIENT_SECRET);
        $client->request('POST', $router->generate('oauth2_token_revoke'), [
            // no token param
        ], [], ['HTTP_Authorization' => $auth]);

        $response = $client->getResponse();
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));

        $json = json_decode($response->getContent(), true);
        $this->assertSame('invalid_request', $json['error']);
    }

    public function testInvalidClientReturnsUnauthorized(): void
    {
        $client = self::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);
        $testHelper = $client->getContainer()->get(TestHelper::class);
        $accessTokenRepository = $client->getContainer()->get(AccessTokenRepository::class);

        list($accessToken) = $accessTokenRepository->findBy(['identifier' => AppFixtures::ACCESS_TOKEN_IDENTIFIER]);
        $token = $testHelper->generateJwtPayloadByAccessToken($accessToken);

        $auth = 'Basic ' . base64_encode(AppFixtures::PRIVATE_CLIENT_IDENTIFIER . ':invalid_secret');
        $client->request('POST', $router->generate('oauth2_token_revoke'), [
            'token' => $token,
        ], [], ['HTTP_Authorization' => $auth]);

        $response = $client->getResponse();
        $this->assertSame(401, $response->getStatusCode());

        $json = json_decode($response->getContent(), true);
        $this->assertSame('invalid_client', $json['error']);
    }

    public function testUnknownTokenReturns200Ok(): void
    {
        $client = self::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $token =
            'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImtpZCI6IjVlZGE5YjQxLTA4NWMtNDAzZi04NWU3LTUwZDNiNDEzMTA4MiJ9.' .
            'eyJhdWQiOiIwMTk4YWQ5NC1jZjk4LTdhZjItOTU1My1lYTYwODM4OTc1ZGYiLCJqdGkiOiI0MWI4ZWFkMGM5ZDFiMDVmNzA3' .
            'MjFmYzg2MmYyZTNjNTZkNDQwYmZiNzQyNDQ2OWQ5NDk0YWE0Yjg0MzJkOGE3OTFmNjY5ODYwMDdjZDFlZSIsImlhdCI6MTc1' .
            'NTY4ODQxMC42NDY2NjgsIm5iZiI6MTc1NTY4ODQxMC42NDY2NzQsImV4cCI6MTc1NTY5MjAxMC42MDk5MTEsInN1YiI6IjAx' .
            'OThiYzQwLTNmZWYtNzYxZS04NjIyLTYzMjVlYzgyMjk2YiIsIm9pZCI6IjAxOThiYzQwLTNmZWYtNzYxZS04NjIyLTYzMjVl' .
            'YzgyMjk2YiIsInNjb3BlcyI6WyJvcGVuaWQiLCJwcm9maWxlIiwiZW1haWwiXSwiZ3JvdXBzIjpbImFkbWluaXN0cmF0b3Ii' .
            'XX0.GtqOcN57bFxvQ_7f-Q9k-d4C7xQTlZHO77_Dsa83R1vm7YSB8S0c1cko3NM2Bsbn4izuJsRS4kBEKt01ArdG97nnw7xy' .
            'VLu_4qMc3Q4RwRYMotIteYcOLxeFCTUYflG4EKbjeyzGp9LoLZQgRi-Yg2FqZ7a75EylV9u1a_p_f-XGqwTIt5xOt-W5scQA' .
            'ItE7FXt4_rE80_jJXq2_g0H1OrdftSW485-SgNmNb3D0Mkt9WfZbdQGmtpo7Sfs-skm-vGMqxdL0SvKNQzofExI5KMs3CeRo' .
            'mLnjGTUXYo7ZTPP7IE10Fkcn9mUwkKnpTIxvz0CyYbz3CabO3AbWecET5A';

        $auth = 'Basic ' . base64_encode(AppFixtures::PRIVATE_CLIENT_IDENTIFIER . ':' . AppFixtures::PRIVATE_CLIENT_SECRET);
        $client->request('POST', $router->generate('oauth2_token_revoke'), [
            'token' => $token,
        ], [], ['HTTP_Authorization' => $auth]);

        $response = $client->getResponse();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('', $response->getContent()); // RFC says empty body
    }

    public function testRevokingExpiredAccessToken(): void
    {
        $client = self::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);
        $testHelper = $client->getContainer()->get(TestHelper::class);
        $accessTokenRepository = $client->getContainer()->get(AccessTokenRepository::class);

        list($accessToken) = $accessTokenRepository->findBy(['identifier' => AppFixtures::EXPIRED_ACCESS_TOKEN_IDENTIFIER]);
        $token = $testHelper->generateJwtPayloadByAccessToken($accessToken);

        $auth = 'Basic ' . base64_encode(AppFixtures::PRIVATE_CLIENT_IDENTIFIER . ':' . AppFixtures::PRIVATE_CLIENT_SECRET);
        $client->request('POST', $router->generate('oauth2_token_revoke'), [
            'token' => $token,
        ], [], ['HTTP_Authorization' => $auth]);

        $response = $client->getResponse();
        $this->assertSame(200, $response->getStatusCode());

        // check DB entity is revoked
        $token = $accessTokenRepository->getByIdentifier(AppFixtures::EXPIRED_ACCESS_TOKEN_IDENTIFIER);

        $this->assertTrue($token->isRevoked());
    }

    public function testRevokingRefreshTokenAlsoRevokesAccessToken(): void
    {
        $client = self::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);
        $testHelper = $client->getContainer()->get(TestHelper::class);
        $refreshTokenRepository = $client->getContainer()->get(RefreshTokenRepository::class);

        list($refreshToken) = $refreshTokenRepository->findBy(['identifier' => AppFixtures::REFRESH_TOKEN_IDENTIFIER]);
        $token = $testHelper->generateEncryptedRefreshTokenPayload($refreshToken);

        $auth = 'Basic ' . base64_encode(AppFixtures::PRIVATE_CLIENT_IDENTIFIER . ':' . AppFixtures::PRIVATE_CLIENT_SECRET);
        $client->request('POST', $router->generate('oauth2_token_revoke'), [
            'token' => $token,
        ], [], ['HTTP_Authorization' => $auth]);

        $response = $client->getResponse();
        $this->assertSame(200, $response->getStatusCode());

        $refreshToken = $refreshTokenRepository->getByIdentifier(AppFixtures::REFRESH_TOKEN_IDENTIFIER);
        $this->assertTrue($refreshToken->isRevoked());

        $accessToken = $refreshToken->getAccessToken();
        $this->assertTrue($accessToken->isRevoked());
    }
}
