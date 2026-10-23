<?php
declare(strict_types=1);

namespace App\Tests\Integration\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\ApplicationTester;

final class SchemaValidateTest extends KernelTestCase
{
    public function testMigratedSchemaMatchesTheEntityMapping(): void
    {
        $application = new Application(self::bootKernel());
        $application->setAutoExit(false);
        // Not CommandTester: the migrations bundle hides its own table from the schema tools in a console event
        // listener, and CommandTester dispatches no console events.
        $tester = new ApplicationTester($application);

        $tester->run(['command' => 'doctrine:schema:validate']);

        $tester->assertCommandIsSuccessful($tester->getDisplay());
    }
}
