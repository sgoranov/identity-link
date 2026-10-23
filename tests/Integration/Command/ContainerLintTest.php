<?php
declare(strict_types=1);

namespace App\Tests\Integration\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ContainerLintTest extends KernelTestCase
{
    public function testTestContainerDefinesOnlyValidServices(): void
    {
        $application = new Application(self::bootKernel());
        $tester = new CommandTester($application->find('lint:container'));

        $tester->execute([]);

        $tester->assertCommandIsSuccessful($tester->getDisplay());
    }
}
