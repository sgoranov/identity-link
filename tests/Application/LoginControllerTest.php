<?php
declare(strict_types=1);

namespace App\Tests\Application;

use App\DataFixtures\AppFixtures;
use App\Tests\SessionHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

class LoginControllerTest extends WebTestCase
{
    use SessionHelper;

    public function testSuccessfulLoginRequest(): void
    {
        $client = static::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $session = $this->getSession($client);
        $session->set('auth_request_params', ['client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER]);
        $session->save();

        $client->request(
            'POST',
            $router->generate('security_login'),
            [
                'login' => [
                    'user_id' => AppFixtures::USER_IDENTIFIER,
                    'password' => AppFixtures::USER_PASSWORD,
                    'submit' => 'submit',
                ],
            ]
        );

        $response = $client->getResponse();

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame($response->headers->get('Location'),
            $router->generate('oauth2_auth', ['client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER]));
    }

    public function testBadCredentialsLoginRequest(): void
    {
        $client = static::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        $session = $this->getSession($client);
        $session->set('auth_request_params', ['client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER]);
        $session->save();

        $client->request(
            'POST',
            $router->generate('security_login'),
            [
                'login' => [
                    'user_id' => 'user',
                    'password' => 'pass',
                    'submit' => 'submit',
                ],
            ]
        );

        $response = $client->getResponse();

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame($response->headers->get('Location'), $router->generate('security_login'));

        $session = $client->getRequest()->getSession();
        $error = $session->get(SecurityRequestAttributes::AUTHENTICATION_ERROR)->getMessage();
        $this->assertSame($error, 'Invalid username or password');
    }
}