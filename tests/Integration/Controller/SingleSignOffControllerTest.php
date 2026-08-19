<?php
declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\DataFixtures\AppFixtures;
use App\Repository\AccessTokenRepository;
use App\Repository\RefreshTokenRepository;
use App\Service\JwtTokenGenerator;
use App\Tests\Helper\BearerAuthorizationTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

class SingleSignOffControllerTest extends WebTestCase
{
    use BearerAuthorizationTrait;

    public function testTokenRevoke()
    {
        $client = static::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        $accessTokenRepository = $client->getContainer()->get(AccessTokenRepository::class);
        $refreshTokenRepository = $client->getContainer()->get(RefreshTokenRepository::class);

        $client->request(
            'POST',
            $router->generate('single_sign_off'),
            [],
            [],
            $this->authorizationHeader($client, AppFixtures::ACCESS_TOKEN_IDENTIFIER),
        );

        $response = $client->getResponse();
        $this->assertSame(200, $response->getStatusCode());

        $entityManager->clear();

        list($accessToken) = $accessTokenRepository->findBy(['identifier' => AppFixtures::ACCESS_TOKEN_IDENTIFIER]);
        $this->assertTrue($accessToken->isRevoked());

        list($accessToken) = $accessTokenRepository->findBy(['identifier' => AppFixtures::EXPIRED_ACCESS_TOKEN_IDENTIFIER]);
        $this->assertFalse($accessToken->isRevoked());

        list($refreshToken) = $refreshTokenRepository->findBy(['identifier' => AppFixtures::REFRESH_TOKEN_IDENTIFIER]);
        $this->assertTrue($refreshToken->isRevoked());

        list($refreshToken) = $refreshTokenRepository->findBy(['identifier' => AppFixtures::ANOTHER_REFRESH_TOKEN_IDENTIFIER]);
        $this->assertTrue($refreshToken->isRevoked());
    }

    public function testWithoutToken(): void
    {
        $client = static::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $client->request('POST', $router->generate('single_sign_off'));

        $response = $client->getResponse();
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testClientCredentialsTokenRevoke(): void
    {
        $client = static::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        $accessTokenRepository = $client->getContainer()->get(AccessTokenRepository::class);
        $refreshTokenRepository = $client->getContainer()->get(RefreshTokenRepository::class);

        $client->request(
            'POST',
            $router->generate('single_sign_off'),
            [],
            [],
            $this->authorizationHeader($client, AppFixtures::CLIENT_CREDENTIALS_ACCESS_TOKEN_IDENTIFIER),
        );

        $response = $client->getResponse();
        $this->assertSame(200, $response->getStatusCode());

        $entityManager->clear();

        list($accessToken) = $accessTokenRepository->findBy(['identifier' => AppFixtures::CLIENT_CREDENTIALS_ACCESS_TOKEN_IDENTIFIER]);
        $this->assertTrue($accessToken->isRevoked());

        list($accessToken) = $accessTokenRepository->findBy(['identifier' => AppFixtures::ACCESS_TOKEN_IDENTIFIER]);
        $this->assertFalse($accessToken->isRevoked());

        list($accessToken) = $accessTokenRepository->findBy(['identifier' => AppFixtures::EXPIRED_ACCESS_TOKEN_IDENTIFIER]);
        $this->assertFalse($accessToken->isRevoked());

        list($refreshToken) = $refreshTokenRepository->findBy(['identifier' => AppFixtures::REFRESH_TOKEN_IDENTIFIER]);
        $this->assertFalse($refreshToken->isRevoked());

        list($refreshToken) = $refreshTokenRepository->findBy(['identifier' => AppFixtures::ANOTHER_REFRESH_TOKEN_IDENTIFIER]);
        $this->assertFalse($refreshToken->isRevoked());
    }

    public function testInvalidOauthToken(): void
    {
        $client = static::createClient();
        $tokenGenerator = $client->getContainer()->get(JwtTokenGenerator::class);
        $router = $client->getContainer()->get(RouterInterface::class);

        $token = $tokenGenerator->createTokenByPayload([
            'aud' => $tokenGenerator->getAudience(),
            'iss' => $tokenGenerator->getIssuer(),
            'iat' => microtime(true),
            'nbf' => microtime(true),
            'exp' => microtime(true) + 60,
            'scope' => 'test',
        ]);

        $client->request(
            'POST',
            $router->generate('single_sign_off'),
            [],
            [],
            ['HTTP_AUTHORIZATION' => "Bearer {$token}"]
        );

        $response = $client->getResponse();
        $this->assertSame(400, $response->getStatusCode());
    }

}
