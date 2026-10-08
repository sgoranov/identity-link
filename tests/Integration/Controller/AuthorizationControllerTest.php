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
use App\Entity\AuthCode;
use App\Entity\AuthorizationSession;
use App\Repository\AuthCodeRepository;
use App\Security\AuthorizationStateEnum;
use App\Tests\Helper\AuthorizationFlowHelper;
use App\Tests\Helper\Mocks\ClientConnectorMock;
use App\Tests\Helper\TestHelper;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

class AuthorizationControllerTest extends WebTestCase
{
    use AuthorizationFlowHelper;

    /** @return array<string, array{bool}> */
    public static function loginFlowProvider(): array
    {
        return [
            'password' => [false],
            'password and 2FA' => [true],
        ];
    }

    /**
     * @dataProvider loginFlowProvider
     */
    public function testSuccessfulCodeRequest(bool $twoFaEnabled): void
    {
        $client = static::createClient();

        $crawler = $this->authorizeWithLogin($client, [
            'client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
            'response_type' => 'code',
            'state' => 'foobar',
        ], $twoFaEnabled);

        $form = $crawler->selectButton('consent[allow]')->form();
        $client->submit($form);

        // after the consent approval, the flow completes and the user is redirected back to the client
        $url = $this->followRedirectsToClient($client);
        $this->assertStringStartsWith(AppFixtures::PRIVATE_CLIENT_REDIRECT_URI, $url);

        $query = [];
        parse_str(parse_url($url, \PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('code', $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertEquals('foobar', $query['state']);

        // at this point the authorization session must be consumed
        $em = $client->getContainer()->get('doctrine')->getManager();
        list($authorizationSession) = $em->getRepository(AuthorizationSession::class)->findAll();

        $this->assertSame(AuthorizationStateEnum::AUTHORIZATION_CODE, $authorizationSession->getAuthorizationState(), 'Authorization state should be AUTHORIZATION_CODE.');
        $this->assertTrue($authorizationSession->isConsumed(), 'AuthorizationSession should be marked as consumed.');
    }

    /**
     * @dataProvider loginFlowProvider
     */
    public function testNewAuthorizationRequestRequiresLoginAgain(bool $twoFaEnabled): void
    {
        $client = static::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $authorizationParams = [
            'client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
            'response_type' => 'code',
            'state' => 'foobar',
        ];

        // complete a full login in the browser and obtain an authorization code
        $crawler = $this->authorizeWithLogin($client, $authorizationParams, $twoFaEnabled);
        $form = $crawler->selectButton('consent[allow]')->form();
        $client->submit($form);
        $url = $this->followRedirectsToClient($client);
        $this->assertStringStartsWith(AppFixtures::PRIVATE_CLIENT_REDIRECT_URI, $url);
        $this->assertStringContainsString('code=', $url);

        // start a new authorization request in the same browser:
        // the previous login must not be reused, so the login flow starts again from the password step
        $client->request('GET', $router->generate('oauth2_auth'), $authorizationParams);
        while ($client->getResponse()->isRedirect()) {
            $location = $client->getResponse()->headers->get('Location');
            $this->assertStringNotContainsString('code=', $location, 'Authorization code issued without a new login.');
            $crawler = $client->followRedirect();
        }

        $this->assertSame('login_with_password', $client->getRequest()->attributes->get('_route'));
        $this->assertCount(1, $crawler->selectButton('login[submit]'));
    }

    /** @return array<string, array{string, bool}> */
    public static function uncompletedLoginProvider(): array
    {
        return [
            'complete before login' => ['oauth2_auth_complete', false],
            'consent before login' => ['oauth2_auth_consent_screen', false],
            'complete before 2FA' => ['oauth2_auth_complete', true],
            'consent before 2FA' => ['oauth2_auth_consent_screen', true],
        ];
    }

    /**
     * @dataProvider uncompletedLoginProvider
     */
    public function testAuthorizationStepsRequireCompletedLogin(string $route, bool $passwordSubmitted): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $router = $client->getContainer()->get(RouterInterface::class);

        $client->request('GET', $router->generate('oauth2_auth'), [
            'client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
            'response_type' => 'code',
            'state' => 'foobar',
        ]);
        $crawler = $client->followRedirect();
        $this->assertSame('login_with_password', $client->getRequest()->attributes->get('_route'));
        $authorizationSessionId = $client->getRequest()->attributes->get('id');

        if ($passwordSubmitted) {
            // the user mock has 2FA enabled by default, so the login stops before the 2FA step
            $form = $crawler->selectButton('login[submit]')->form([
                'login[user_id]' => AppFixtures::USER_IDENTIFIER,
                'login[password]' => AppFixtures::USER_PASSWORD,
            ]);
            $client->submit($form);
            $this->assertResponseRedirects($router->generate('login_2fa_initiate', ['id' => $authorizationSessionId]));
        }

        $client->request('GET', $router->generate($route, ['id' => $authorizationSessionId]));

        $this->assertResponseStatusCodeSame(400);
        $location = (string) $client->getResponse()->headers->get('Location');
        $this->assertStringNotContainsString('code=', $location);

        $em = $client->getContainer()->get('doctrine')->getManager();
        $authorizationSession = $em->getRepository(AuthorizationSession::class)->find($authorizationSessionId);
        $this->assertFalse($authorizationSession->isConsumed(), 'AuthorizationSession must not be consumed before the login is completed.');
    }

    public function testClientWithoutConsentReceivesCodeWithoutConsentScreen(): void
    {
        $client = static::createClient();
        $clientConnector = $client->getContainer()->get(ClientConnectorInterface::class);
        self::assertInstanceOf(ClientConnectorMock::class, $clientConnector);
        $clientConnector->setConsentRequired(false);

        $this->authorizeWithLogin($client, [
            'client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
            'response_type' => 'code',
            'state' => 'foobar',
        ]);

        $url = $this->followRedirectsToClient($client);
        $this->assertStringStartsWith(AppFixtures::PRIVATE_CLIENT_REDIRECT_URI, $url);
        $this->assertStringContainsString('code=', $url);
    }

    /** @return array<string, array{string}> */
    public static function stepsAfterConsentProvider(): array
    {
        return [
            'complete the flow' => ['login_complete'],
            'issue the code' => ['oauth2_auth_complete'],
        ];
    }

    /**
     * @dataProvider stepsAfterConsentProvider
     */
    public function testStepsAfterConsentRequireConsent(string $route): void
    {
        $client = static::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        // the login is done and the consent screen is shown, but consent is not given yet
        $this->authorizeWithLogin($client, [
            'client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
            'response_type' => 'code',
            'state' => 'foobar',
        ]);
        $this->assertSame('oauth2_auth_consent_screen', $client->getRequest()->attributes->get('_route'));
        $authorizationSessionId = $client->getRequest()->attributes->get('id');

        $client->request('GET', $router->generate($route, ['id' => $authorizationSessionId]));

        $this->assertResponseStatusCodeSame(400);
        $em = $client->getContainer()->get('doctrine')->getManager();
        $authorizationSession = $em->getRepository(AuthorizationSession::class)->find($authorizationSessionId);
        $this->assertFalse($authorizationSession->isConsumed(), 'No code must be issued before consent.');
    }

    public function testConsentCannotBeGivenTwice(): void
    {
        $client = static::createClient();

        $crawler = $this->authorizeWithLogin($client, [
            'client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
            'response_type' => 'code',
            'state' => 'foobar',
        ]);
        $consentUrl = $client->getRequest()->getUri();

        // give consent, but stop before the flow completes
        $client->submit($crawler->selectButton('consent[allow]')->form());
        $this->assertResponseRedirects();

        $client->request('GET', $consentUrl);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testDeniedConsentReturnsErrorToClient(): void
    {
        $client = static::createClient();

        $crawler = $this->authorizeWithLogin($client, [
            'client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
            'response_type' => 'code',
            'state' => 'foobar',
            'redirect_uri' => AppFixtures::PRIVATE_CLIENT_REDIRECT_URI,
        ]);
        $authorizationSessionId = $client->getRequest()->attributes->get('id');

        $client->submit($crawler->selectButton('consent[deny]')->form());

        $url = $this->followRedirectsToClient($client);
        $this->assertStringStartsWith(AppFixtures::PRIVATE_CLIENT_REDIRECT_URI, $url);

        $query = [];
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        $this->assertSame('access_denied', $query['error'] ?? null);
        $this->assertSame('foobar', $query['state'] ?? null);
        $this->assertArrayNotHasKey('code', $query);

        $em = $client->getContainer()->get('doctrine')->getManager();
        $authorizationSession = $em->getRepository(AuthorizationSession::class)->find($authorizationSessionId);
        $this->assertTrue($authorizationSession->isConsumed(), 'A denied authorization session must not be usable again.');
    }

    /** @return array<string, array{string}> */
    public static function boundStepProvider(): array
    {
        return [
            'password' => ['login_with_password'],
            'consent' => ['oauth2_auth_consent_screen'],
            'authorization code' => ['oauth2_auth_complete'],
        ];
    }

    /**
     * @dataProvider boundStepProvider
     */
    public function testAuthorizationCannotBeContinuedFromAnotherBrowser(string $route): void
    {
        $client = static::createClient();
        $stepUrl = $this->reachStep($client, $route);
        $authorizationSessionId = basename((string) parse_url($stepUrl, \PHP_URL_PATH));

        // another browser, i.e. without the cookie set when the authorization request started
        $cookies = $client->getCookieJar()->all();
        $client->getCookieJar()->clear();
        $client->request('GET', $stepUrl);

        $this->assertResponseStatusCodeSame(400);
        $em = $client->getContainer()->get('doctrine')->getManager();
        $authorizationSession = $em->getRepository(AuthorizationSession::class)->find($authorizationSessionId);
        $this->assertFalse($authorizationSession->isConsumed(), 'Another browser must not be able to end the authorization.');

        // the browser that started the authorization can still continue it
        foreach ($cookies as $cookie) {
            $client->getCookieJar()->set($cookie);
        }
        $client->request('GET', $stepUrl);

        if ($route === 'oauth2_auth_complete') {
            $this->assertStringContainsString('code=', (string) $client->getResponse()->headers->get('Location'));
        } else {
            $this->assertResponseIsSuccessful();
        }
    }

    public function testBindingCookieIsRemovedWhenTheCodeIsIssued(): void
    {
        $client = static::createClient();
        $stepUrl = $this->reachStep($client, 'oauth2_auth_complete');
        $cookieName = 'authorization_session_' . basename((string) parse_url($stepUrl, \PHP_URL_PATH));
        $this->assertNotNull($client->getCookieJar()->get($cookieName));

        $client->request('GET', $stepUrl);

        $this->assertStringContainsString('code=', (string) $client->getResponse()->headers->get('Location'));
        $this->assertNull($client->getCookieJar()->get($cookieName));
    }

    /**
     * Goes through the authorization flow up to $route and returns its URL without requesting it.
     */
    private function reachStep(KernelBrowser $client, string $route): string
    {
        $authorizationParams = [
            'client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
            'response_type' => 'code',
            'state' => 'foobar',
        ];

        if ($route === 'login_with_password') {
            $client->disableReboot();
            $client->request('GET', $client->getContainer()->get(RouterInterface::class)->generate('oauth2_auth'), $authorizationParams);
            $this->assertResponseRedirects();

            return (string) $client->getResponse()->headers->get('Location');
        }

        $crawler = $this->authorizeWithLogin($client, $authorizationParams);
        $this->assertSame('oauth2_auth_consent_screen', $client->getRequest()->attributes->get('_route'));
        if ($route === 'oauth2_auth_consent_screen') {
            return $client->getRequest()->getUri();
        }

        // consent -> login_complete -> oauth2_auth_complete
        $client->submit($crawler->selectButton('consent[allow]')->form());
        $client->followRedirect();
        $this->assertSame('login_complete', $client->getRequest()->attributes->get('_route'));
        $this->assertResponseRedirects();

        return (string) $client->getResponse()->headers->get('Location');
    }

    public function testSuccessfulCodeRequestWithAlternateRedirectUri(): void
    {
        $client = static::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);
        $redirectUri = AppFixtures::MULTI_REDIRECT_CLIENT_REDIRECT_URIS[1];

        $crawler = $this->authorizeWithLogin($client, [
            'client_id' => AppFixtures::MULTI_REDIRECT_CLIENT_IDENTIFIER,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'state' => 'foobar',
        ]);

        $form = $crawler->selectButton('consent[allow]')->form();
        $client->submit($form);

        $url = $this->followRedirectsToClient($client);
        $this->assertStringStartsWith($redirectUri, $url);
    }

    public function testSuccessfulPKCEAuthCodeRequest(): void
    {
        $client = static::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);
        $authCodeRepository = $client->getContainer()->get(AuthCodeRepository::class);
        $testHelper = $client->getContainer()->get(TestHelper::class);

        $state = bin2hex(random_bytes(20));
        $codeVerifier = bin2hex(random_bytes(64));
        $codeChallengeMethod = 'S256';

        $codeChallenge = strtr(
            rtrim(base64_encode(hash('sha256', $codeVerifier, true)), '='),
            '+/',
            '-_'
        );

        $crawler = $this->authorizeWithLogin($client, [
            'client_id' => AppFixtures::PUBLIC_CLIENT_IDENTIFIER,
            'response_type' => 'code',
            'scope' => '',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => $codeChallengeMethod,
        ]);

        $form = $crawler->selectButton('consent[allow]')->form();
        $client->submit($form);

        // after the consent approval, the flow completes and the user is redirected back to the client
        $url = $this->followRedirectsToClient($client);

        $this->assertStringStartsWith(AppFixtures::PUBLIC_CLIENT_REDIRECT_URI, $url);

        $query = [];
        parse_str(parse_url($url, \PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertSame($state, $query['state']);

        $this->assertArrayHasKey('code', $query);
        $payload = json_decode($testHelper->decryptPayload($query['code']), true);

        $this->assertArrayHasKey('code_challenge', $payload);
        $this->assertArrayHasKey('code_challenge_method', $payload);
        $this->assertSame($codeChallenge, $payload['code_challenge']);
        $this->assertSame($codeChallengeMethod, $payload['code_challenge_method']);

        $authCode = $authCodeRepository->findOneBy(['identifier' => $payload['auth_code_id']]);

        $this->assertInstanceOf(AuthCode::class, $authCode);
        $this->assertSame(AppFixtures::PUBLIC_CLIENT_IDENTIFIER, $authCode->getClientIdentifier());
    }

    public function testAuthCodeRequestWithPublicClientWithoutCodeChallengeWhenTheChallengeIsRequiredForPublicClients(): void
    {
        $client = static::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $client->request(
            'GET',
            $router->generate('oauth2_auth'),
            [
                'client_id' => AppFixtures::PUBLIC_CLIENT_IDENTIFIER,
                'response_type' => 'code',
                'scope' => '',
                'state' => bin2hex(random_bytes(20)),
            ]
        );

        $response = $client->getResponse();

        $this->assertSame(400, $response->getStatusCode());

        $this->assertSame('application/json', $response->headers->get('Content-Type'));

        $jsonResponse = json_decode($response->getContent(), true);

        $this->assertSame('invalid_request', $jsonResponse['error']);
        $this->assertSame('The request is missing a required parameter, includes an invalid parameter value, includes a parameter more than once, or is otherwise malformed.', $jsonResponse['error_description']);
        $this->assertSame('Code challenge must be provided for public clients', $jsonResponse['hint']);
    }

    public function testAuthCodeRequestWithClientWhoIsNotAllowedToMakeARequestWithPlainCodeChallengeMethod(): void
    {
        $client = static::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $state = bin2hex(random_bytes(20));
        $codeVerifier = bin2hex(random_bytes(32));
        $codeChallengeMethod = 'plain';
        $codeChallenge = strtr(rtrim(base64_encode($codeVerifier), '='), '+/', '-_');

        $client->request(
            'GET',
            $router->generate('oauth2_auth'),
            [
                'client_id' => AppFixtures::PUBLIC_CLIENT_IDENTIFIER,
                'response_type' => 'code',
                'scope' => '',
                'state' => $state,
                'code_challenge' => $codeChallenge,
                'code_challenge_method' => $codeChallengeMethod,
            ]
        );

        $response = $client->getResponse();

        $this->assertSame(400, $response->getStatusCode());

        $this->assertSame('application/json', $response->headers->get('Content-Type'));

        $jsonResponse = json_decode($response->getContent(), true);

        $this->assertSame('invalid_request', $jsonResponse['error']);
        $this->assertSame('The request is missing a required parameter, includes an invalid parameter value, includes a parameter more than once, or is otherwise malformed.', $jsonResponse['error_description']);
        $this->assertSame('Plain code challenge method is not allowed for this client', $jsonResponse['hint']);
    }

//
//    public function testCodeRequestRedirectToResolutionUri(): void
//    {
//        $this->client
//            ->getContainer()
//            ->get('event_dispatcher')
//            ->addListener(OAuth2Events::AUTHORIZATION_REQUEST_RESOLVE, static function (AuthorizationRequestResolveEvent $event): void {
//                $event->setResponse(new Response(null, 302, [
//                    'Location' => '/authorize/consent',
//                ]));
//            });
//
//        $this->client->request(
//            'GET',
//            '/authorize',
//            [
//                'client_id' => FixtureFactory::FIXTURE_CLIENT_FIRST,
//                'response_type' => 'code',
//                'state' => 'foobar',
//                'redirect_uri' => FixtureFactory::FIXTURE_CLIENT_FIRST_REDIRECT_URI,
//                'scope' => FixtureFactory::FIXTURE_SCOPE_FIRST . ' ' . FixtureFactory::FIXTURE_SCOPE_SECOND,
//            ]
//        );
//
//        $response = $this->client->getResponse();
//
//        $this->assertSame(302, $response->getStatusCode());
//        $redirectUri = $response->headers->get('Location');
//        $this->assertEquals('/authorize/consent', $redirectUri);
//    }

    public function testFailedCodeRequestRedirectWithFakedRedirectUri(): void
    {
        $client = static::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $client->request(
            'GET',
            $router->generate('oauth2_auth'),
            [
                'client_id' => AppFixtures::PUBLIC_CLIENT_IDENTIFIER,
                'response_type' => 'code',
                'state' => 'foobar',
                'redirect_uri' => 'https://example.org/oauth2/malicious-uri',
            ]
        );

        $response = $client->getResponse();

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));

        $jsonResponse = json_decode($response->getContent(), true);

        $this->assertSame('invalid_client', $jsonResponse['error']);
        $this->assertSame('Client authentication failed', $jsonResponse['error_description']);
    }

    public function testFailedCodeRequestWithUnregisteredRedirectUriForMultiRedirectClient(): void
    {
        $client = static::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $client->request(
            'GET',
            $router->generate('oauth2_auth'),
            [
                'client_id' => AppFixtures::MULTI_REDIRECT_CLIENT_IDENTIFIER,
                'response_type' => 'code',
                'state' => 'foobar',
                'redirect_uri' => 'http://localhost/multi/unknown',
            ]
        );

        $response = $client->getResponse();

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));

        $jsonResponse = json_decode($response->getContent(), true);

        $this->assertSame('invalid_client', $jsonResponse['error']);
        $this->assertSame('Client authentication failed', $jsonResponse['error_description']);
    }

    public function testFailedAuthorizeRequest(): void
    {
        $client = static::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $client->request(
            'GET',
            $router->generate('oauth2_auth'),
        );

        $response = $client->getResponse();

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));

        $jsonResponse = json_decode($response->getContent(), true);

        $this->assertSame('invalid_request', $jsonResponse['error']);
        $this->assertSame('The request is missing a required parameter, includes an invalid parameter value, includes a parameter more than once, or is otherwise malformed.', $jsonResponse['error_description']);
        $this->assertSame('Check the `response_type` parameter', $jsonResponse['hint']);
    }

    public function testAuthorizeRequestWithInvalidScopes(): void
    {
        $client = static::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $client->request(
            'GET',
            $router->generate('oauth2_auth'),
            [
                'client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
                'response_type' => 'code',
                'state' => 'foobar',
                'scope' => 'test1 test2',
            ]
        );

        $response = $client->getResponse();

        $this->assertSame(302, $response->getStatusCode());
        $redirectUri = $response->headers->get('Location');

        $redirectUri = str_replace('http://localhost?', '', $redirectUri);
        $this->assertStringStartsWith(
            'state=foobar&error=invalid_scope&error_description=The+requested+scope+is+invalid%2C+unknown%2C+or+malformed',
            $redirectUri
        );
    }
}
