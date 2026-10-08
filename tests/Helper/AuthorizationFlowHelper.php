<?php
declare(strict_types=1);

namespace App\Tests\Helper;

use App\Api\Contract\UserConnectorInterface;
use App\DataFixtures\AppFixtures;
use App\Tests\Helper\Mocks\UserConnectorMock;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Routing\RouterInterface;

trait AuthorizationFlowHelper
{
    /**
     * Starts an authorization request and logs in through the login form, as a browser would.
     * With $twoFaEnabled, the user also passes the two-factor step.
     * Returns the page shown after the login (the consent screen for clients that require consent).
     *
     * @param array<string, mixed> $authorizationParams
     */
    private function authorizeWithLogin(KernelBrowser $client, array $authorizationParams, bool $twoFaEnabled = false): Crawler
    {
        // keep the same container across requests so the mock configuration persists
        $client->disableReboot();
        $userConnector = $client->getContainer()->get(UserConnectorInterface::class);
        self::assertInstanceOf(UserConnectorMock::class, $userConnector);
        $userConnector->setTwoFaEnabled($twoFaEnabled);

        $router = $client->getContainer()->get(RouterInterface::class);
        $client->request('GET', $router->generate('oauth2_auth'), $authorizationParams);
        $crawler = $this->followRedirects($client);

        $form = $crawler->selectButton('login[submit]')->form([
            'login[user_id]' => AppFixtures::USER_IDENTIFIER,
            'login[password]' => AppFixtures::USER_PASSWORD,
        ]);
        $client->submit($form);

        if ($twoFaEnabled) {
            $this->passTwoFactorStep($client);
        }

        return $this->followRedirects($client);
    }

    /**
     * Follows the redirect to the external 2FA service and simulates it sending the user back
     * to the login flow after a successful second factor.
     */
    private function passTwoFactorStep(KernelBrowser $client): void
    {
        $router = $client->getContainer()->get(RouterInterface::class);

        // password step -> 2FA initiate
        self::assertTrue($client->getResponse()->isRedirect());
        $client->followRedirect();
        self::assertSame('login_2fa_initiate', $client->getRequest()->attributes->get('_route'));

        // 2FA initiate -> external 2FA service
        self::assertTrue($client->getResponse()->isRedirect());
        $twoFaUrl = $client->getResponse()->headers->get('Location');
        $twoFaId = basename((string) parse_url($twoFaUrl, \PHP_URL_PATH));

        $authorizationSessionId = $client->getRequest()->attributes->get('id');
        $client->request('GET', $router->generate('login_2fa_complete', ['id' => $authorizationSessionId]), ['id' => $twoFaId]);
    }

    private function followRedirects(KernelBrowser $client): Crawler
    {
        $crawler = $client->getCrawler();
        while ($client->getResponse()->isRedirect()) {
            $crawler = $client->followRedirect();
        }

        return $crawler;
    }
}
