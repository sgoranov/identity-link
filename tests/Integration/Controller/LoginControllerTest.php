<?php
declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\DataFixtures\AppFixtures;
use App\Entity\AuthRequest;
use App\Security\LoginStateEnum;
use App\Tests\Helper\SessionHelper;
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

        // hit the initial authorization endpoint
        // this will redirect to the login page
        $client->request('GET', $router->generate('oauth2_auth'), [
            'client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
            'response_type' => 'code',
            'state' => 'foobar',
        ]);

        // expect to redirect to the login dispatch
        // this will redirect to the login page
        $this->assertResponseRedirects();
        $client->request('GET', $client->getResponse()->headers->get('Location'));
        $crawler = $client->followRedirect();

        $location = $client->getResponse()->headers->get('Location');
        $crawler = $client->request('GET', $location);

        // submit the login form
        $form = $crawler->selectButton('login[submit]')->form([
            'login[user_id]' => AppFixtures::USER_IDENTIFIER,
            'login[password]' => AppFixtures::USER_PASSWORD,
        ]);
        $client->submit($form);

        $em = $client->getContainer()->get('doctrine')->getManager();
        list($authRequest) = $em->getRepository(AuthRequest::class)->findAll();

        $this->assertEquals(LoginStateEnum::PASSWORD->value, $authRequest->getLoginState()->value, 'Login state should be PASSWORD.');
        $this->assertNull($authRequest->getConsentApproved(), 'Consent was still not approved.');
        $this->assertFalse($authRequest->isConsumed(), 'AuthRequest should be marked as false.');
    }

    public function testBadCredentialsLoginRequest(): void
    {
        $client = static::createClient();
        $router = $client->getContainer()->get(RouterInterface::class);

        // hit the initial authorization endpoint
        // this will redirect to the login page
        $client->request('GET', $router->generate('oauth2_auth'), [
            'client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
            'response_type' => 'code',
            'state' => 'foobar',
        ]);

        // expect to redirect to the login dispatch
        // this will redirect to the login page
        $this->assertResponseRedirects();
        $client->request('GET', $client->getResponse()->headers->get('Location'));
        $crawler = $client->followRedirect();

        $location = $client->getResponse()->headers->get('Location');
        $crawler = $client->request('GET', $location);

        // submit the login form
        $form = $crawler->selectButton('login[submit]')->form([
            'login[user_id]' => 'user',
            'login[password]' => 'pass',
        ]);
        $client->submit($form);

        $session = $client->getRequest()->getSession();

        $errors = $session->getFlashBag()->get('error');
        $this->assertNotEmpty($errors);
        list($error) = $errors;
        $this->assertStringContainsString('login.invalid_credentials', $error['key']);
    }
}