<?php
/**
 * Copyright for held by Robin Chalas as part of project thephpleague/oauth2-server-bundle
 * https://github.com/thephpleague/oauth2-server-bundle
 *
 * Copyright (c) 2020 Robin Chalas
 * Portions Copyright (c) 2018-2020 Trikoder
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\DataFixtures\AppFixtures;
use App\LeagueOAuth2\Entity\GrantTypeEntity;
use App\Repository\AuthCodeRepository;
use App\Repository\RefreshTokenRepository;
use App\Tests\Helper\TestHelper;
use League\OAuth2\Server\RequestAccessTokenEvent;
use League\OAuth2\Server\RequestEvent;
use League\OAuth2\Server\RequestRefreshTokenEvent;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouterInterface;

final class TokenControllerTest extends WebTestCase
{
    public function testSuccessfulClientCredentialsRequest(): void
    {
        $client = TokenControllerTest::createClient();
        $eventDispatcher = $client->getContainer()->get(EventDispatcherInterface::class);
        $router = $client->getContainer()->get(RouterInterface::class);

        $accessToken = null;
        $wasRequestAccessTokenEventDispatched = false;
        $eventDispatcher->addListener(RequestEvent::ACCESS_TOKEN_ISSUED, static function (RequestAccessTokenEvent $event) use (&$wasRequestAccessTokenEventDispatched, &$accessToken): void {
            $wasRequestAccessTokenEventDispatched = true;
            $accessToken = $event->getAccessToken();
        });

        $client->request('POST', $router->generate('oauth2_token'), [
            'client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
            'client_secret' => AppFixtures::PRIVATE_CLIENT_SECRET,
            'grant_type' => 'client_credentials',
        ]);

        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json; charset=UTF-8', $response->headers->get('Content-Type'));

        $jsonResponse = json_decode($response->getContent(), true);

        $this->assertSame('Bearer', $jsonResponse['token_type']);
        $this->assertLessThanOrEqual(3600, $jsonResponse['expires_in']);
        $this->assertGreaterThan(0, $jsonResponse['expires_in']);
        $this->assertNotEmpty($jsonResponse['access_token']);
        $this->assertArrayNotHasKey('refresh_token', $jsonResponse);

        $this->assertTrue($wasRequestAccessTokenEventDispatched);

        $this->assertSame(AppFixtures::PRIVATE_CLIENT_IDENTIFIER, $accessToken->getClient()->getIdentifier());
        $this->assertNull($accessToken->getUserIdentifier());
    }

    public function testSuccessfulPasswordRequest(): void
    {
        $client = TokenControllerTest::createClient();
        $eventDispatcher = $client->getContainer()->get(EventDispatcherInterface::class);
        $router = $client->getContainer()->get(RouterInterface::class);

        $wasRequestAccessTokenEventDispatched = false;
        $wasRequestRefreshTokenEventDispatched = false;
        $accessToken = null;
        $refreshToken = null;

        $eventDispatcher->addListener(RequestEvent::ACCESS_TOKEN_ISSUED, static function (RequestAccessTokenEvent $event) use (&$wasRequestAccessTokenEventDispatched, &$accessToken): void {
            $wasRequestAccessTokenEventDispatched = true;
            $accessToken = $event->getAccessToken();
        });

        $eventDispatcher->addListener(RequestEvent::REFRESH_TOKEN_ISSUED, static function (RequestRefreshTokenEvent $event) use (&$wasRequestRefreshTokenEventDispatched, &$refreshToken): void {
            $wasRequestRefreshTokenEventDispatched = true;
            $refreshToken = $event->getRefreshToken();
        });

        $client->request('POST', $router->generate('oauth2_token'), [
            'client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
            'client_secret' => AppFixtures::PRIVATE_CLIENT_SECRET,
            'grant_type' => GrantTypeEntity::PASSWORD,
            'username' => AppFixtures::USER_IDENTIFIER,
            'password' => AppFixtures::USER_PASSWORD,
        ]);

        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json; charset=UTF-8', $response->headers->get('Content-Type'));

        $jsonResponse = json_decode($response->getContent(), true);

        $this->assertSame('Bearer', $jsonResponse['token_type']);
        $this->assertLessThanOrEqual(3600, $jsonResponse['expires_in']);
        $this->assertGreaterThan(0, $jsonResponse['expires_in']);
        $this->assertNotEmpty($jsonResponse['access_token']);
        $this->assertNotEmpty($jsonResponse['refresh_token']);

        $this->assertTrue($wasRequestAccessTokenEventDispatched);
        $this->assertTrue($wasRequestRefreshTokenEventDispatched);

        $this->assertSame(AppFixtures::PRIVATE_CLIENT_IDENTIFIER, $accessToken->getClient()->getIdentifier());
        $this->assertSame(AppFixtures::USER_IDENTIFIER, $accessToken->getUserIdentifier());
        $this->assertSame($accessToken->getIdentifier(), $refreshToken->getAccessToken()->getIdentifier());
    }

    public function testPasswordRequestWithInvalidPassword(): void
    {
        $client = TokenControllerTest::createClient();
        $eventDispatcher = $client->getContainer()->get(EventDispatcherInterface::class);
        $router = $client->getContainer()->get(RouterInterface::class);

        $wasRequestAccessTokenEventDispatched = false;
        $wasRequestRefreshTokenEventDispatched = false;
        $accessToken = null;
        $refreshToken = null;

        $eventDispatcher->addListener(RequestEvent::ACCESS_TOKEN_ISSUED, static function (RequestAccessTokenEvent $event) use (&$wasRequestAccessTokenEventDispatched, &$accessToken): void {
            $wasRequestAccessTokenEventDispatched = true;
            $accessToken = $event->getAccessToken();
        });

        $eventDispatcher->addListener(RequestEvent::REFRESH_TOKEN_ISSUED, static function (RequestRefreshTokenEvent $event) use (&$wasRequestRefreshTokenEventDispatched, &$refreshToken): void {
            $wasRequestRefreshTokenEventDispatched = true;
            $refreshToken = $event->getRefreshToken();
        });

        $client->request('POST', $router->generate('oauth2_token'), [
            'client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
            'client_secret' => AppFixtures::PRIVATE_CLIENT_SECRET,
            'grant_type' => GrantTypeEntity::PASSWORD,
            'username' => AppFixtures::USER_IDENTIFIER,
            'password' => 'invalid-password',
        ]);

        $response = $client->getResponse();

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));

        $jsonResponse = json_decode($response->getContent(), true);

        $this->assertSame('invalid_grant', $jsonResponse['error']);
        $this->assertSame('The user credentials were incorrect.', $jsonResponse['message']);
        $this->assertSame('The user credentials were incorrect.', $jsonResponse['message']);
    }

    public function testSuccessfulRefreshTokenRequest(): void
    {
        $client = TokenControllerTest::createClient();
        $eventDispatcher = $client->getContainer()->get(EventDispatcherInterface::class);
        /** @var TestHelper $testHelper */
        $testHelper = $client->getContainer()->get(TestHelper::class);
        $router = $client->getContainer()->get(RouterInterface::class);

        $refreshTokenRepository = $client->getContainer()->get(RefreshTokenRepository::class);
        list($refreshToken) = $refreshTokenRepository->findBy(['identifier' => AppFixtures::REFRESH_TOKEN_IDENTIFIER]);

        $wasRequestAccessTokenEventDispatched = false;
        $wasRequestRefreshTokenEventDispatched = false;
        $accessToken = null;
        $refreshTokenEntity = null;

        $eventDispatcher->addListener(RequestEvent::ACCESS_TOKEN_ISSUED, static function (RequestAccessTokenEvent $event) use (&$wasRequestAccessTokenEventDispatched, &$accessToken): void {
            $wasRequestAccessTokenEventDispatched = true;
            $accessToken = $event->getAccessToken();
        });

        $eventDispatcher->addListener(RequestEvent::REFRESH_TOKEN_ISSUED, static function (RequestRefreshTokenEvent $event) use (&$wasRequestRefreshTokenEventDispatched, &$refreshTokenEntity): void {
            $wasRequestRefreshTokenEventDispatched = true;
            $refreshTokenEntity = $event->getRefreshToken();
        });

        $client->request('POST', $router->generate('oauth2_token'), [
            'client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
            'client_secret' => AppFixtures::PRIVATE_CLIENT_SECRET,
            'grant_type' => GrantTypeEntity::REFRESH_TOKEN,
            'refresh_token' => $testHelper->generateEncryptedRefreshTokenPayload($refreshToken),
        ]);

        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json; charset=UTF-8', $response->headers->get('Content-Type'));

        $jsonResponse = json_decode($response->getContent(), true);

        $this->assertSame('Bearer', $jsonResponse['token_type']);
        $this->assertLessThanOrEqual(3600, $jsonResponse['expires_in']);
        $this->assertGreaterThan(0, $jsonResponse['expires_in']);
        $this->assertNotEmpty($jsonResponse['access_token']);
        $this->assertNotEmpty($jsonResponse['refresh_token']);

        $this->assertTrue($wasRequestAccessTokenEventDispatched);
        $this->assertTrue($wasRequestRefreshTokenEventDispatched);

        $this->assertSame($refreshToken->getAccessToken()->getClientIdentifier(), $accessToken->getClient()->getIdentifier());
        $this->assertSame($accessToken->getIdentifier(), $refreshTokenEntity->getAccessToken()->getIdentifier());
    }

    public function testSuccessfulAuthorizationCodeRequest(): void
    {
        $client = TokenControllerTest::createClient();
        $eventDispatcher = $client->getContainer()->get(EventDispatcherInterface::class);
        $testHelper = $client->getContainer()->get(TestHelper::class);
        $router = $client->getContainer()->get(RouterInterface::class);

        $authCodeRepository = $client->getContainer()->get(AuthCodeRepository::class);
        list($authCode) = $authCodeRepository->findBy(['identifier' => AppFixtures::AUTH_CODE_PRIVATE_CLIENT_IDENTIFIER]);

        $client->request('POST', $router->generate('oauth2_token'), [
            'client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
            'client_secret' => AppFixtures::PRIVATE_CLIENT_SECRET,
            'grant_type' => GrantTypeEntity::AUTHORIZATION_CODE,
            'redirect_uri' => AppFixtures::PRIVATE_CLIENT_REDIRECT_URI,
            'code' => $testHelper->generateEncryptedAuthCodePayload($authCode),
        ]);

        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json; charset=UTF-8', $response->headers->get('Content-Type'));

        $jsonResponse = json_decode($response->getContent(), true);

        $this->assertSame('Bearer', $jsonResponse['token_type']);
        $this->assertLessThanOrEqual(3600, $jsonResponse['expires_in']);
        $this->assertGreaterThan(0, $jsonResponse['expires_in']);
        $this->assertNotEmpty($jsonResponse['access_token']);
    }

    public function testSuccessfulAuthorizationCodeRequestWithPublicClient(): void
    {
        $client = TokenControllerTest::createClient();
        $eventDispatcher = $client->getContainer()->get(EventDispatcherInterface::class);
        $testHelper = $client->getContainer()->get(TestHelper::class);
        $router = $client->getContainer()->get(RouterInterface::class);

        $wasRequestAccessTokenEventDispatched = false;
        $wasRequestRefreshTokenEventDispatched = false;
        $accessToken = null;
        $refreshToken = null;

        $eventDispatcher->addListener(RequestEvent::ACCESS_TOKEN_ISSUED, static function (RequestAccessTokenEvent $event) use (&$wasRequestAccessTokenEventDispatched, &$accessToken): void {
            $wasRequestAccessTokenEventDispatched = true;
            $accessToken = $event->getAccessToken();
        });

        $eventDispatcher->addListener(RequestEvent::REFRESH_TOKEN_ISSUED, static function (RequestRefreshTokenEvent $event) use (&$wasRequestRefreshTokenEventDispatched, &$refreshToken): void {
            $wasRequestRefreshTokenEventDispatched = true;
            $refreshToken = $event->getRefreshToken();
        });

        $authCodeRepository = $client->getContainer()->get(AuthCodeRepository::class);
        list($authCode) = $authCodeRepository->findBy(['identifier' => AppFixtures::AUTH_CODE_PUBLIC_CLIENT_IDENTIFIER]);

        $client->request('POST', $router->generate('oauth2_token'), [
            'client_id' => AppFixtures::PUBLIC_CLIENT_IDENTIFIER,
            'grant_type' => GrantTypeEntity::AUTHORIZATION_CODE,
            'redirect_uri' => AppFixtures::PUBLIC_CLIENT_REDIRECT_URI,
            'code' => $testHelper->generateEncryptedAuthCodePayload($authCode),
        ]);

        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json; charset=UTF-8', $response->headers->get('Content-Type'));

        $jsonResponse = json_decode($response->getContent(), true);

        $this->assertSame('Bearer', $jsonResponse['token_type']);
        $this->assertLessThanOrEqual(3600, $jsonResponse['expires_in']);
        $this->assertGreaterThan(0, $jsonResponse['expires_in']);
        $this->assertNotEmpty($jsonResponse['access_token']);
        $this->assertNotEmpty($jsonResponse['refresh_token']);

        $this->assertTrue($wasRequestAccessTokenEventDispatched);
        $this->assertTrue($wasRequestRefreshTokenEventDispatched);

        $this->assertSame($authCode->getClientIdentifier(), $accessToken->getClient()->getIdentifier());
        $this->assertSame($authCode->getUserIdentifier(), $accessToken->getUserIdentifier());
        $this->assertSame($accessToken->getIdentifier(), $refreshToken->getAccessToken()->getIdentifier());
    }

    public function testFailedTokenRequest(): void
    {
        $client = TokenControllerTest::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $client->request('POST', $router->generate('oauth2_token'));

        $response = $client->getResponse();

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));

        $jsonResponse = json_decode($response->getContent(), true);

        $this->assertSame('unsupported_grant_type', $jsonResponse['error']);
        $this->assertSame('The authorization grant type is not supported by the authorization server.', $jsonResponse['message']);
        $this->assertSame('Check that all required parameters have been provided', $jsonResponse['hint']);
    }

    public function testFailedClientCredentialsTokenRequest(): void
    {
        $client = TokenControllerTest::createClient();
        $eventDispatcher = $client->getContainer()->get(EventDispatcherInterface::class);
        $testHelper = $client->getContainer()->get(TestHelper::class);
        $router = $client->getContainer()->get(RouterInterface::class);

        $wasClientAuthenticationEventDispatched = false;

        $eventDispatcher->addListener(RequestEvent::CLIENT_AUTHENTICATION_FAILED, static function (RequestEvent $event) use (&$wasClientAuthenticationEventDispatched, &$accessToken): void {
            $wasClientAuthenticationEventDispatched = true;
        });

        $client->request('POST', $router->generate('oauth2_token'), [
            'client_id' => 'foo',
            'client_secret' => 'wrong',
            'grant_type' => GrantTypeEntity::CLIENT_CREDENTIALS,
        ]);

        $response = $client->getResponse();

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));

        $jsonResponse = json_decode($response->getContent(), true);

        $this->assertSame('invalid_client', $jsonResponse['error']);
        $this->assertSame('Client authentication failed', $jsonResponse['message']);

        $this->assertTrue($wasClientAuthenticationEventDispatched);
    }
}
