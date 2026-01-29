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
use App\Entity\AuthCode;
use App\Entity\AuthRequest;
use App\Repository\AuthCodeRepository;
use App\Security\User;
use App\Tests\Helper\TestHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

class AuthorizationControllerTest extends WebTestCase
{
    public function testSuccessfulCodeRequest(): void
    {
        $client = static::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $user = new User(AppFixtures::USER_IDENTIFIER);
        $client->loginUser($user, 'secured');

        // hit the initial authorization endpoint
        $client->request('GET', $router->generate('oauth2_auth'), [
            'client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
            'response_type' => 'code',
            'state' => 'foobar',
        ]);

        // expect redirect to /oauth2/auth/{id}
        $this->assertResponseRedirects();
        $client->request(
            'GET',
            $client->getResponse()->headers->get('Location')
        );
        $crawler = $client->followRedirect();

        $form = $crawler->selectButton('consent[allow]')->form();
        $client->submit($form);

        // after the consent approval, the client is redirected to the authorization endpoint
        $this->assertResponseRedirects();
        $client->followRedirect();
        $url = $client->getResponse()->headers->get('Location');
        $this->assertStringStartsWith(AppFixtures::PRIVATE_CLIENT_REDIRECT_URI, $url);

        $query = [];
        parse_str(parse_url($url, \PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('code', $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertEquals('foobar', $query['state']);

        // at this point the auth request must be consumed
        $em = $client->getContainer()->get('doctrine')->getManager();
        list($authRequest) = $em->getRepository(AuthRequest::class)->findAll();

        $this->assertNull($authRequest->getLoginState(), 'Login state should be null for already logged-in user.');
        $this->assertTrue($authRequest->getConsentApproved(), 'Consent should be approved.');
        $this->assertTrue($authRequest->isConsumed(), 'AuthRequest should be marked as consumed.');
    }

    public function testSuccessfulCodeRequestWithAlternateRedirectUri(): void
    {
        $client = static::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);
        $redirectUri = AppFixtures::MULTI_REDIRECT_CLIENT_REDIRECT_URIS[1];

        $user = new User(AppFixtures::USER_IDENTIFIER);
        $client->loginUser($user, 'secured');

        $client->request('GET', $router->generate('oauth2_auth'), [
            'client_id' => AppFixtures::MULTI_REDIRECT_CLIENT_IDENTIFIER,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'state' => 'foobar',
        ]);

        $this->assertResponseRedirects();
        $client->request(
            'GET',
            $client->getResponse()->headers->get('Location')
        );
        $crawler = $client->followRedirect();

        $form = $crawler->selectButton('consent[allow]')->form();
        $client->submit($form);

        $this->assertResponseRedirects();
        $client->followRedirect();
        $url = $client->getResponse()->headers->get('Location');
        $this->assertStringStartsWith($redirectUri, $url);
    }

    public function testSuccessfulPKCEAuthCodeRequest(): void
    {
        $client = static::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);
        $authCodeRepository = $client->getContainer()->get(AuthCodeRepository::class);
        $testHelper = $client->getContainer()->get(TestHelper::class);

        $user = new User(AppFixtures::USER_IDENTIFIER);
        $client->loginUser($user, 'secured');

        $state = bin2hex(random_bytes(20));
        $codeVerifier = bin2hex(random_bytes(64));
        $codeChallengeMethod = 'S256';

        $codeChallenge = strtr(
            rtrim(base64_encode(hash('sha256', $codeVerifier, true)), '='),
            '+/',
            '-_'
        );

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

        // expect redirect to /oauth2/auth/{id}
        $this->assertResponseRedirects();
        $client->request(
            'GET',
            $client->getResponse()->headers->get('Location')
        );
        $crawler = $client->followRedirect();

        $form = $crawler->selectButton('consent[allow]')->form();
        $client->submit($form);

        // after the consent approval, the client is redirected to the authorization endpoint
        $this->assertResponseRedirects();
        $client->followRedirect();
        $url = $client->getResponse()->headers->get('Location');

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

        $user = new User(AppFixtures::USER_IDENTIFIER);
        $client->loginUser($user, 'secured');

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
        $this->assertSame('The request is missing a required parameter, includes an invalid parameter value, includes a parameter more than once, or is otherwise malformed.', $jsonResponse['message']);
        $this->assertSame('Code challenge must be provided for public clients', $jsonResponse['hint']);
    }

    public function testAuthCodeRequestWithClientWhoIsNotAllowedToMakeARequestWithPlainCodeChallengeMethod(): void
    {
        $client = static::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $user = new User(AppFixtures::USER_IDENTIFIER);
        $client->loginUser($user, 'secured');

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
        $this->assertSame('The request is missing a required parameter, includes an invalid parameter value, includes a parameter more than once, or is otherwise malformed.', $jsonResponse['message']);
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

        $user = new User(AppFixtures::USER_IDENTIFIER);
        $client->loginUser($user, 'secured');

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
        $this->assertSame('Client authentication failed', $jsonResponse['message']);
    }

    public function testFailedCodeRequestWithUnregisteredRedirectUriForMultiRedirectClient(): void
    {
        $client = static::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $user = new User(AppFixtures::USER_IDENTIFIER);
        $client->loginUser($user, 'secured');

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
        $this->assertSame('Client authentication failed', $jsonResponse['message']);
    }

    public function testFailedAuthorizeRequest(): void
    {
        $client = static::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $user = new User(AppFixtures::USER_IDENTIFIER);
        $client->loginUser($user, 'secured');
        
        $client->request(
            'GET',
            $router->generate('oauth2_auth'),
        );

        $response = $client->getResponse();

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));

        $jsonResponse = json_decode($response->getContent(), true);

        $this->assertSame('unsupported_grant_type', $jsonResponse['error']);
        $this->assertSame('The authorization grant type is not supported by the authorization server.', $jsonResponse['message']);
        $this->assertSame('Check that all required parameters have been provided', $jsonResponse['hint']);
    }

    public function testAuthorizeRequestWithInvalidScopes(): void
    {
        $client = static::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $user = new User(AppFixtures::USER_IDENTIFIER);
        $client->loginUser($user, 'secured');

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
            'error=invalid_scope&error_description=The+requested+scope+is+invalid%2C+unknown%2C+or+malformed',
            $redirectUri
        );
    }
}
