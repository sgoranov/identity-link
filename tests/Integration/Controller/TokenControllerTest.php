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

use App\Api\Contract\ClientConnectorInterface;
use App\DataFixtures\AppFixtures;
use App\LeagueOAuth2\Entity\GrantTypeEntity;
use App\Repository\AuthCodeRepository;
use App\Repository\RefreshTokenRepository;
use App\Security\Jwt\JwtConfig;
use App\Tests\Helper\AuthorizationFlowHelper;
use App\Tests\Helper\TestHelper;
use App\Tests\Helper\Mocks\ClientConnectorMock;
use App\Tests\Helper\Mocks\UserConnectorMock;
use App\Api\Contract\UserConnectorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use League\OAuth2\Server\RequestAccessTokenEvent;
use League\OAuth2\Server\RequestEvent;
use League\OAuth2\Server\RequestRefreshTokenEvent;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouterInterface;

final class TokenControllerTest extends WebTestCase
{
    use AuthorizationFlowHelper;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['HTTP_HOST'] = 'localhost';
    }

    public function testSuccessfulClientCredentialsRequest(): void
    {
        $client = TokenControllerTest::createClient();
        $eventDispatcher = $client->getContainer()->get(EventDispatcherInterface::class);
        $router = $client->getContainer()->get(RouterInterface::class);

        $accessToken = null;
        $wasRequestAccessTokenEventDispatched = false;
        $eventDispatcher->addListener(RequestAccessTokenEvent::class, static function (RequestAccessTokenEvent $event) use (&$wasRequestAccessTokenEventDispatched, &$accessToken): void {
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

        $this->assertSame(
            implode(' ', array_map('strval', $accessToken->getScopes())),
            $jsonResponse['scope'],
        );

        $this->assertSame(AppFixtures::PRIVATE_CLIENT_IDENTIFIER, $accessToken->getClient()->getIdentifier());
        $this->assertNull($accessToken->getUserIdentifier());
    }

    public function testClientCredentialsRequestRejectsUnknownAudience(): void
    {
        $client = static::createClient();
        $clientConnector = $client->getContainer()->get(ClientConnectorInterface::class);
        self::assertInstanceOf(ClientConnectorMock::class, $clientConnector);
        $clientConnector->setAudienceOverride('https://unknown.example');
        $router = $client->getContainer()->get(RouterInterface::class);

        $client->request('POST', $router->generate('oauth2_token'), [
            'client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
            'client_secret' => AppFixtures::PRIVATE_CLIENT_SECRET,
            'grant_type' => GrantTypeEntity::CLIENT_CREDENTIALS,
        ]);

        $response = $client->getResponse();
        $jsonResponse = json_decode($response->getContent(), true);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('invalid_target', $jsonResponse['error']);
        self::assertSame('The requested resource is invalid.', $jsonResponse['error_description']);
        self::assertSame('The requested audience is not supported.', $jsonResponse['hint']);
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

        $eventDispatcher->addListener(RequestAccessTokenEvent::class, static function (RequestAccessTokenEvent $event) use (&$wasRequestAccessTokenEventDispatched, &$accessToken): void {
            $wasRequestAccessTokenEventDispatched = true;
            $accessToken = $event->getAccessToken();
        });

        $eventDispatcher->addListener(RequestRefreshTokenEvent::class, static function (RequestRefreshTokenEvent $event) use (&$wasRequestRefreshTokenEventDispatched, &$refreshToken): void {
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

        $eventDispatcher->addListener(RequestAccessTokenEvent::class, static function (RequestAccessTokenEvent $event) use (&$wasRequestAccessTokenEventDispatched, &$accessToken): void {
            $wasRequestAccessTokenEventDispatched = true;
            $accessToken = $event->getAccessToken();
        });

        $eventDispatcher->addListener(RequestRefreshTokenEvent::class, static function (RequestRefreshTokenEvent $event) use (&$wasRequestRefreshTokenEventDispatched, &$refreshToken): void {
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
        $this->assertSame('The user credentials were incorrect.', $jsonResponse['error_description']);
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

        $eventDispatcher->addListener(RequestAccessTokenEvent::class, static function (RequestAccessTokenEvent $event) use (&$wasRequestAccessTokenEventDispatched, &$accessToken): void {
            $wasRequestAccessTokenEventDispatched = true;
            $accessToken = $event->getAccessToken();
        });

        $eventDispatcher->addListener(RequestRefreshTokenEvent::class, static function (RequestRefreshTokenEvent $event) use (&$wasRequestRefreshTokenEventDispatched, &$refreshTokenEntity): void {
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

    public function testAuthorizationCodeTokenRequestRejectsUnknownAudience(): void
    {
        $client = static::createClient();
        $clientConnector = $client->getContainer()->get(ClientConnectorInterface::class);
        self::assertInstanceOf(ClientConnectorMock::class, $clientConnector);
        $clientConnector->setAudienceOverride('https://unknown.example');
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
        $jsonResponse = json_decode($response->getContent(), true);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('invalid_target', $jsonResponse['error']);
        self::assertSame('The requested resource is invalid.', $jsonResponse['error_description']);
        self::assertSame('The requested audience is not supported.', $jsonResponse['hint']);
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

        $eventDispatcher->addListener(RequestAccessTokenEvent::class, static function (RequestAccessTokenEvent $event) use (&$wasRequestAccessTokenEventDispatched, &$accessToken): void {
            $wasRequestAccessTokenEventDispatched = true;
            $accessToken = $event->getAccessToken();
        });

        $eventDispatcher->addListener(RequestRefreshTokenEvent::class, static function (RequestRefreshTokenEvent $event) use (&$wasRequestRefreshTokenEventDispatched, &$refreshToken): void {
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

    public function testSuccessfulAuthorizationCodeRequestWithMultiRedirectUri(): void
    {
        $client = TokenControllerTest::createClient();
        $testHelper = $client->getContainer()->get(TestHelper::class);
        $router = $client->getContainer()->get(RouterInterface::class);

        $authCodeRepository = $client->getContainer()->get(AuthCodeRepository::class);
        list($authCode) = $authCodeRepository->findBy(['identifier' => AppFixtures::AUTH_CODE_MULTI_CLIENT_IDENTIFIER]);

        $client->request('POST', $router->generate('oauth2_token'), [
            'client_id' => AppFixtures::MULTI_REDIRECT_CLIENT_IDENTIFIER,
            'client_secret' => AppFixtures::MULTI_REDIRECT_CLIENT_SECRET,
            'grant_type' => GrantTypeEntity::AUTHORIZATION_CODE,
            'redirect_uri' => AppFixtures::MULTI_REDIRECT_CLIENT_REDIRECT_URIS[1],
            'code' => $testHelper->generateEncryptedAuthCodePayload($authCode),
        ]);

        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json; charset=UTF-8', $response->headers->get('Content-Type'));

        $jsonResponse = json_decode($response->getContent(), true);

        $this->assertSame('Bearer', $jsonResponse['token_type']);
        $this->assertNotEmpty($jsonResponse['access_token']);
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
        $this->assertSame('The authorization grant type is not supported by the authorization server.', $jsonResponse['error_description']);
        $this->assertSame('Check that all required parameters have been provided', $jsonResponse['hint']);
    }

    public function testFailedClientCredentialsTokenRequest(): void
    {
        $client = TokenControllerTest::createClient();
        $eventDispatcher = $client->getContainer()->get(EventDispatcherInterface::class);
        $testHelper = $client->getContainer()->get(TestHelper::class);
        $router = $client->getContainer()->get(RouterInterface::class);

        $wasClientAuthenticationEventDispatched = false;

        $eventDispatcher->addListener(RequestEvent::class, static function (RequestEvent $event) use (&$wasClientAuthenticationEventDispatched, &$accessToken): void {
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
        $this->assertSame('Client authentication failed', $jsonResponse['error_description']);

        $this->assertTrue($wasClientAuthenticationEventDispatched);
    }

    public function testAuthorizationCodeFlowReturnsIdToken(): void
    {
        [$idToken, $jwtConfig] = $this->requestIdToken();

        // Decode ID token to inspect claims
        [$header, $payload] = explode('.', $idToken);
        $decodedHeader = json_decode(base64_decode(strtr($header, '-_', '+/')), true);
        $decodedPayload = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

        $this->assertSame('RS256', $decodedHeader['alg']);
        $this->assertSame($jwtConfig->getKid(), $decodedHeader['kid']);
        $this->assertArrayHasKey('sub', $decodedPayload);
        $this->assertSame(AppFixtures::USER_IDENTIFIER, $decodedPayload['sub']);
        $this->assertSame($jwtConfig->getIssuer(), $decodedPayload['iss']);
        $this->assertSame(AppFixtures::PRIVATE_CLIENT_IDENTIFIER, $decodedPayload['aud']);
        $this->assertArrayHasKey('exp', $decodedPayload);
    }

    public function testAuthorizationCodeFlowReturnsVerifiableIdToken(): void
    {
        [$idToken, $jwtConfig] = $this->requestIdToken();
        $publicKey = file_get_contents($jwtConfig->getPublicKey());

        $this->assertNotFalse($publicKey);

        $decoded = JWT::decode($idToken, new Key($publicKey, 'RS256'));

        $this->assertSame($jwtConfig->getIssuer(), $decoded->iss);
        $this->assertSame(AppFixtures::PRIVATE_CLIENT_IDENTIFIER, $decoded->aud);
        $this->assertSame(AppFixtures::USER_IDENTIFIER, $decoded->sub);
    }

    public function testAuthorizationCodeFlowUsesClientAndResourceAudiencesForDifferentTokens(): void
    {
        [$idToken, $jwtConfig, $accessToken] = $this->requestIdToken();
        $idTokenPayload = $this->decodeJwtPayload($idToken);
        $accessTokenPayload = $this->decodeJwtPayload($accessToken);

        // The relying-party client consumes the ID token.
        $this->assertSame(AppFixtures::PRIVATE_CLIENT_IDENTIFIER, $idTokenPayload['aud']);

        // The protected resource consumes the access token.
        $this->assertSame($jwtConfig->getAudience(), $accessTokenPayload['aud']);
        $this->assertSame(AppFixtures::PRIVATE_CLIENT_IDENTIFIER, $accessTokenPayload['client_id']);
    }

    /**
     * @dataProvider nonceProvider
     */
    public function testAuthorizationCodeFlowIncludesNonceInIdToken(string $nonce): void
    {
        $client = static::createClient();
        $response = $this->requestTokenThroughAuthorizationFlow($client, ['nonce' => $nonce]);

        $this->assertArrayHasKey('id_token', $response);
        $payload = $this->decodeJwtPayload($response['id_token']);

        // The nonce must be echoed exactly as sent (OIDC Core 3.1.3.7), without trimming or re-encoding.
        $this->assertArrayHasKey('nonce', $payload);
        $this->assertSame($nonce, $payload['nonce']);
    }

    /** @return array<string, array{string}> */
    public static function nonceProvider(): array
    {
        return [
            'random' => [bin2hex(random_bytes(16))],
            'reserved and whitespace characters' => [' a+b/c=d&e?f%20 '],
            'unicode' => ['nonce-ünïcödé-✓'],
            'maximum length' => [str_repeat('n', 255)],
        ];
    }

    public function testIdTokenOmitsNonceWhenNotRequested(): void
    {
        $client = static::createClient();
        $response = $this->requestTokenThroughAuthorizationFlow($client);

        $this->assertArrayHasKey('id_token', $response);
        $this->assertArrayNotHasKey('nonce', $this->decodeJwtPayload($response['id_token']));
    }

    public function testIdTokenOmitsEmptyNonce(): void
    {
        $client = static::createClient();
        $response = $this->requestTokenThroughAuthorizationFlow($client, ['nonce' => '']);

        $this->assertArrayHasKey('id_token', $response);
        $this->assertArrayNotHasKey('nonce', $this->decodeJwtPayload($response['id_token']));
    }

    public function testNonceDoesNotCarryOverToNextAuthorization(): void
    {
        $client = static::createClient();
        // Keep one kernel, and so the same service instances, across both flows, as a long-running worker would.
        $client->disableReboot();

        $first = $this->requestTokenThroughAuthorizationFlow($client, ['nonce' => 'first-nonce']);
        $second = $this->requestTokenThroughAuthorizationFlow($client);

        $this->assertSame('first-nonce', $this->decodeJwtPayload($first['id_token'])['nonce']);
        $this->assertArrayNotHasKey('nonce', $this->decodeJwtPayload($second['id_token']));
    }

    public function testRefreshedIdTokenOmitsNonce(): void
    {
        $client = static::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);
        $response = $this->requestTokenThroughAuthorizationFlow($client, ['nonce' => 'original-nonce']);

        $client->request('POST', $router->generate('oauth2_token'), [
            'client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
            'client_secret' => AppFixtures::PRIVATE_CLIENT_SECRET,
            'grant_type' => GrantTypeEntity::REFRESH_TOKEN,
            'refresh_token' => $response['refresh_token'],
        ]);

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $refreshed = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        // A refreshed ID token should not contain a nonce (OIDC Core 12.2).
        $this->assertArrayHasKey('id_token', $refreshed);
        $this->assertArrayNotHasKey('nonce', $this->decodeJwtPayload($refreshed['id_token']));
    }

    public function testNonceIsIgnoredWithoutOpenidScope(): void
    {
        $client = static::createClient();
        $response = $this->requestTokenThroughAuthorizationFlow($client, [
            'scope' => 'profile',
            'nonce' => str_repeat('n', 256),
        ]);

        $this->assertArrayNotHasKey('id_token', $response);
    }

    /**
     * @dataProvider invalidNonceProvider
     */
    public function testAuthorizationRejectsInvalidNonce(mixed $nonce): void
    {
        $client = static::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $client->request('GET', $router->generate('oauth2_auth'), [
            'client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
            'response_type' => 'code',
            'scope' => 'openid',
            'state' => 'foobar',
            'nonce' => $nonce,
            'redirect_uri' => AppFixtures::PRIVATE_CLIENT_REDIRECT_URI,
        ]);

        // The client and redirect URI are valid, so the error goes back to the client (RFC 6749 4.1.2.1).
        $this->assertSame(302, $client->getResponse()->getStatusCode());
        $location = (string) $client->getResponse()->headers->get('Location');
        $this->assertStringStartsWith(AppFixtures::PRIVATE_CLIENT_REDIRECT_URI, $location);

        parse_str((string) parse_url($location, \PHP_URL_QUERY), $query);
        $this->assertSame('invalid_request', $query['error']);
        $this->assertSame('foobar', $query['state']);
        $this->assertArrayNotHasKey('code', $query);
    }

    /** @return array<string, array{mixed}> */
    public static function invalidNonceProvider(): array
    {
        return [
            'longer than 255 bytes' => [str_repeat('n', 256)],
            'multibyte longer than 255 bytes' => [str_repeat('✓', 86)],
            'array' => [['a', 'b']],
        ];
    }

    public function testNoIdTokenWithoutOpenidScope(): void
    {
        $client = static::createClient();
        $testHelper = $client->getContainer()->get(TestHelper::class);
        $router = $client->getContainer()->get(RouterInterface::class);
        $authCodeRepository = $client->getContainer()->get(AuthCodeRepository::class);
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);

        // Ensure auth code has empty scopes to simulate a minimal OIDC flow
        list($authCode) = $authCodeRepository->findBy(['identifier' => AppFixtures::AUTH_CODE_PRIVATE_CLIENT_IDENTIFIER]);
        $authCode->setScopes(json_encode([]));
        $entityManager->persist($authCode);
        $entityManager->flush();

        $client->request('POST', $router->generate('oauth2_token'), [
            'client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
            'client_secret' => AppFixtures::PRIVATE_CLIENT_SECRET,
            'grant_type' => GrantTypeEntity::AUTHORIZATION_CODE,
            'redirect_uri' => AppFixtures::PRIVATE_CLIENT_REDIRECT_URI,
            'code' => $testHelper->generateEncryptedAuthCodePayload($authCode),
        ]);

        $response = $client->getResponse();
        $this->assertSame(200, $response->getStatusCode());

        $jsonResponse = json_decode($response->getContent(), true);
        $this->assertArrayNotHasKey('id_token', $jsonResponse);
    }

    public function testNoIdTokenWhenClientHasNoOpenIdConnectScopes(): void
    {
        $client = static::createClient();
        $clientConnector = $client->getContainer()->get(ClientConnectorInterface::class);
        self::assertInstanceOf(ClientConnectorMock::class, $clientConnector);
        $clientConnector->setScopes([]);

        $this->requestAuthorizationCodeTokenWithScopes($client, [
            'openid',
            'profile',
            'email',
            'offline_access',
        ], false, [], ['openid']);
    }

    public function testIdTokenWhenClientGroupGrantsOpenIdConnectScopes(): void
    {
        $client = static::createClient();
        $clientConnector = $client->getContainer()->get(ClientConnectorInterface::class);
        self::assertInstanceOf(ClientConnectorMock::class, $clientConnector);
        // The connector represents scopes inherited from the client's group.
        $clientConnector->setScopes([
            'openid',
            'profile',
            'email',
            'offline_access',
        ]);

        $this->requestAuthorizationCodeTokenWithScopes($client, [
            'openid',
            'profile',
            'email',
            'offline_access',
        ], true);
    }

    public function testIdTokenClaimsRequireClientUserAndRequestedScopes(): void
    {
        $client = static::createClient();
        $scopes = ['openid', 'profile', 'email', 'offline_access'];
        $response = $this->requestAuthorizationCodeTokenWithScopes($client, $scopes, true, $scopes, $scopes, $scopes);
        $payload = $this->decodeJwtPayload($response['id_token']);

        self::assertSame('User Name', $payload['name']);
        self::assertSame('username@email.com', $payload['email']);
    }

    /**
     * @dataProvider missingClaimScopeProvider
     * @param list<string> $clientScopes
     * @param list<string> $userScopes
     * @param list<string> $requestedScopes
     */
    public function testIdTokenClaimsAreOmittedWhenScopesAreMissing(
        array $clientScopes,
        array $userScopes,
        array $requestedScopes,
    ): void {
        $client = static::createClient();
        $response = $this->requestAuthorizationCodeTokenWithScopes(
            $client,
            ['openid', 'profile', 'email', 'offline_access'],
            true,
            $clientScopes,
            $userScopes,
            $requestedScopes,
        );
        $payload = $this->decodeJwtPayload($response['id_token']);

        self::assertArrayNotHasKey('name', $payload);
        self::assertArrayNotHasKey('email', $payload);
    }

    /** @return array<string, array{list<string>, list<string>, list<string>}> */
    public static function missingClaimScopeProvider(): array
    {
        $all = ['openid', 'profile', 'email', 'offline_access'];
        $withoutProfileAndEmail = ['openid', 'offline_access'];

        return [
            'client' => [$withoutProfileAndEmail, $all, $all],
            'user' => [$all, $withoutProfileAndEmail, $all],
            'requested' => [$all, $all, $withoutProfileAndEmail],
        ];
    }

    /** @return array<string, mixed> */
    private function requestAuthorizationCodeTokenWithScopes(
        KernelBrowser $client,
        array $scopes,
        bool $expectIdToken,
        ?array $clientScopes = null,
        ?array $userScopes = null,
        ?array $requestedScopes = null,
    ): array {
        $container = $client->getContainer();
        $clientConnector = $container->get(ClientConnectorInterface::class);
        self::assertInstanceOf(ClientConnectorMock::class, $clientConnector);
        $clientConnector->setScopes($clientScopes ?? ['openid', 'profile', 'email', 'offline_access']);
        $userConnector = $container->get(UserConnectorInterface::class);
        self::assertInstanceOf(UserConnectorMock::class, $userConnector);
        $userConnector->setScopes($userScopes ?? ['openid']);
        $testHelper = $container->get(TestHelper::class);
        $router = $container->get(RouterInterface::class);
        $authCodeRepository = $container->get(AuthCodeRepository::class);
        $entityManager = $container->get(EntityManagerInterface::class);

        [$authCode] = $authCodeRepository->findBy([
            'identifier' => AppFixtures::AUTH_CODE_PRIVATE_CLIENT_IDENTIFIER,
        ]);
        $authCode->setScopes(json_encode($requestedScopes ?? $scopes, JSON_THROW_ON_ERROR));
        $entityManager->persist($authCode);
        $entityManager->flush();

        $client->request('POST', $router->generate('oauth2_token'), [
            'client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
            'client_secret' => AppFixtures::PRIVATE_CLIENT_SECRET,
            'grant_type' => GrantTypeEntity::AUTHORIZATION_CODE,
            'redirect_uri' => AppFixtures::PRIVATE_CLIENT_REDIRECT_URI,
            'code' => $testHelper->generateEncryptedAuthCodePayload($authCode),
        ]);

        self::assertSame(200, $client->getResponse()->getStatusCode());

        $jsonResponse = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        if ($expectIdToken) {
            self::assertArrayHasKey('id_token', $jsonResponse);
            self::assertNotEmpty($jsonResponse['id_token']);
        } else {
            self::assertArrayNotHasKey('id_token', $jsonResponse);
        }

        return $jsonResponse;
    }

    /**
     * Runs the browser authorization flow (authorize, consent, redirect) and exchanges the code for tokens.
     *
     * @param array<string, mixed> $authorizationParams overrides for the authorization request
     * @return array<string, mixed>
     */
    private function requestTokenThroughAuthorizationFlow(KernelBrowser $client, array $authorizationParams = []): array
    {
        $router = $client->getContainer()->get(RouterInterface::class);

        $crawler = $this->authorizeWithLogin($client, $authorizationParams + [
            'client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
            'response_type' => 'code',
            'scope' => 'openid',
            'state' => 'foobar',
            'redirect_uri' => AppFixtures::PRIVATE_CLIENT_REDIRECT_URI,
        ]);

        $client->submit($crawler->selectButton('consent[allow]')->form());

        $this->assertResponseRedirects();
        $client->followRedirect();

        $query = [];
        parse_str((string) parse_url($client->getResponse()->headers->get('Location'), \PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('code', $query);

        $client->request('POST', $router->generate('oauth2_token'), [
            'client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
            'client_secret' => AppFixtures::PRIVATE_CLIENT_SECRET,
            'grant_type' => GrantTypeEntity::AUTHORIZATION_CODE,
            'redirect_uri' => AppFixtures::PRIVATE_CLIENT_REDIRECT_URI,
            'code' => $query['code'],
        ]);

        $this->assertSame(200, $client->getResponse()->getStatusCode());

        return json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array{string, JwtConfig, string} */
    private function requestIdToken(): array
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $testHelper = $container->get(TestHelper::class);
        $router = $container->get(RouterInterface::class);
        $authCodeRepository = $container->get(AuthCodeRepository::class);

        list($authCode) = $authCodeRepository->findBy([
            'identifier' => AppFixtures::AUTH_CODE_PRIVATE_CLIENT_IDENTIFIER,
        ]);

        $client->request('POST', $router->generate('oauth2_token'), [
            'client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
            'client_secret' => AppFixtures::PRIVATE_CLIENT_SECRET,
            'grant_type' => GrantTypeEntity::AUTHORIZATION_CODE,
            'redirect_uri' => AppFixtures::PRIVATE_CLIENT_REDIRECT_URI,
            'code' => $testHelper->generateEncryptedAuthCodePayload($authCode),
        ]);

        $response = $client->getResponse();
        $this->assertSame(200, $response->getStatusCode());

        $jsonResponse = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('id_token', $jsonResponse);
        $this->assertNotEmpty($jsonResponse['id_token']);

        return [
            $jsonResponse['id_token'],
            $container->get(JwtConfig::class),
            $jsonResponse['access_token'],
        ];
    }

    private function decodeJwtPayload(string $jwt): array
    {
        $payload = explode('.', $jwt)[1];

        return json_decode(
            base64_decode(strtr($payload, '-_', '+/')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
