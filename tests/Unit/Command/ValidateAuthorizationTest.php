<?php
declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\ValidateAuthorization;
use App\Security\Authorization\AuthorizationRegistry;
use App\Security\Authorization\Loader\AuthorizationLoaderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Exception\ParseException;

final class ValidateAuthorizationTest extends TestCase
{
    public function testListsTheAudiencesOfAValidConfiguration(): void
    {
        $loader = $this->createStub(AuthorizationLoaderInterface::class);
        $loader->method('load')->willReturn(new AuthorizationRegistry([
            'https://api.example' => [],
            'https://app.example' => [],
        ]));
        $tester = new CommandTester(new ValidateAuthorization($loader));

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertSame(
            "The authorization configuration is valid. Audiences:\n - https://api.example\n - https://app.example\n",
            $tester->getDisplay(true),
        );
    }

    /**
     * @dataProvider configurationErrorProvider
     */
    public function testFailsWithTheMessageOfAConfigurationError(\Throwable $error): void
    {
        $loader = $this->createStub(AuthorizationLoaderInterface::class);
        $loader->method('load')->willThrowException($error);
        $tester = new CommandTester(new ValidateAuthorization($loader));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString($error->getMessage(), $tester->getDisplay());
    }

    /** @return array<string, array{\Throwable}> */
    public static function configurationErrorProvider(): array
    {
        return [
            'invalid configuration' => [new \InvalidArgumentException('Audience "" must not be empty.')],
            'invalid YAML' => [new ParseException('Unable to parse at line 3.')],
        ];
    }
}
