<?php
declare(strict_types=1);

namespace App\Tests\Integration\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ValidateAuthorizationTest extends KernelTestCase
{
    public function testValidatesTheConfigurationLoadedByTheApplication(): void
    {
        $application = new Application(self::bootKernel());
        $tester = new CommandTester($application->find('identity-link:validate-authorization'));

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString(' - https://example.com/identity-link', $tester->getDisplay());
        self::assertStringContainsString(' - https://login-only.example', $tester->getDisplay());
    }
}
