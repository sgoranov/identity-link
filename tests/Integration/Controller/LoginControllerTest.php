<?php
declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\DataFixtures\AppFixtures;
use App\Entity\AuthorizationSession;
use App\Security\AuthorizationStateEnum;
use App\Tests\Helper\SessionHelper;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

class LoginControllerTest extends WebTestCase
{
    use SessionHelper;

    protected function setUp(): void
    {
        $this->clearRateLimits();
    }

    protected function tearDown(): void
    {
        // the IP limit reached here would block the logins of the following tests
        $this->clearRateLimits();
        parent::tearDown();
    }

    private function clearRateLimits(): void
    {
        self::bootKernel();
        self::getContainer()->get('rate_limiter_redis')->clear();
        self::ensureKernelShutdown();
    }

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

        while ($client->getResponse()->isRedirect()) {
            $crawler = $client->followRedirect();
        }

        // submit the login form
        $form = $crawler->selectButton('login[submit]')->form([
            'login[user_id]' => AppFixtures::USER_IDENTIFIER,
            'login[password]' => AppFixtures::USER_PASSWORD,
        ]);
        $client->submit($form);

        $em = $client->getContainer()->get('doctrine')->getManager();
        list($authorizationSession) = $em->getRepository(AuthorizationSession::class)->findAll();

        $this->assertEquals(AuthorizationStateEnum::PASSWORD->value, $authorizationSession->getAuthorizationState()->value, 'Login state should be PASSWORD.');
        $this->assertFalse($authorizationSession->isConsumed(), 'AuthorizationSession should be marked as false.');
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

        while ($client->getResponse()->isRedirect()) {
            $crawler = $client->followRedirect();
        }

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

    public function testSuccessfulLoginsAreNotLimited(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // more than the limits of failed logins (20 per IP, 5 per username)
        for ($i = 0; $i < 25; $i++) {
            $this->login($client, AppFixtures::USER_IDENTIFIER, AppFixtures::USER_PASSWORD);
            $this->assertNoLoginError($client);
        }
    }

    public function testFailedLoginsLimitTheUsername(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        for ($i = 0; $i < 5; $i++) {
            $this->login($client, AppFixtures::USER_IDENTIFIER, 'wrong');
            $this->assertLoginError($client, 'login.invalid_credentials');
        }

        // the correct password is not checked anymore
        $this->login($client, AppFixtures::USER_IDENTIFIER, AppFixtures::USER_PASSWORD);
        $this->assertLoginError($client, 'login.too_many_attempts');

        // other usernames are not limited
        $this->login($client, 'other-user', 'wrong');
        $this->assertLoginError($client, 'login.invalid_credentials');
    }

    public function testSuccessfulLoginResetsTheUsernameLimit(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // 8 failed logins in total, but never 5 in a row
        for ($round = 0; $round < 2; $round++) {
            for ($i = 0; $i < 4; $i++) {
                $this->login($client, AppFixtures::USER_IDENTIFIER, 'wrong');
                $this->assertLoginError($client, 'login.invalid_credentials');
            }

            $this->login($client, AppFixtures::USER_IDENTIFIER, AppFixtures::USER_PASSWORD);
            $this->assertNoLoginError($client);
        }
    }

    public function testFailedLoginsLimitTheIp(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // 20 failed logins, spread over usernames so that none reaches its own limit
        for ($i = 0; $i < 20; $i++) {
            $this->login($client, 'user-' . intdiv($i, 4), 'wrong');
            $this->assertLoginError($client, 'login.invalid_credentials');
        }

        $this->login($client, AppFixtures::USER_IDENTIFIER, AppFixtures::USER_PASSWORD);
        $this->assertLoginError($client, 'login.too_many_attempts');
    }

    /**
     * Starts a new authorization request and submits the login form.
     */
    private function login(KernelBrowser $client, string $userId, string $password): void
    {
        $router = $client->getContainer()->get(RouterInterface::class);

        $client->request('GET', $router->generate('oauth2_auth'), [
            'client_id' => AppFixtures::PRIVATE_CLIENT_IDENTIFIER,
            'response_type' => 'code',
            'state' => 'foobar',
        ]);
        $crawler = $client->followRedirect();

        $client->submit($crawler->selectButton('login[submit]')->form([
            'login[user_id]' => $userId,
            'login[password]' => $password,
        ]));
    }

    private function assertLoginError(KernelBrowser $client, string $expectedKey): void
    {
        // a failed login redirects back to the login page with the error in a flash message
        $this->assertResponseRedirects();
        $this->assertStringContainsString('/login/password/', (string) $client->getResponse()->headers->get('Location'));

        $errors = $client->getRequest()->getSession()->getFlashBag()->get('error');
        $this->assertCount(1, $errors);
        $this->assertSame($expectedKey, $errors[0]['key']);
    }

    private function assertNoLoginError(KernelBrowser $client): void
    {
        $this->assertResponseRedirects();
        $this->assertStringNotContainsString('/login/password/', (string) $client->getResponse()->headers->get('Location'));
        $this->assertSame([], $client->getRequest()->getSession()->getFlashBag()->get('error'));
    }
}
