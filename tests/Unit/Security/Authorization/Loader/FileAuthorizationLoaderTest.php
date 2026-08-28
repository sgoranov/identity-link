<?php
declare(strict_types=1);

namespace App\Tests\Unit\Security\Authorization\Loader;

use App\Security\Authorization\AuthorizationRegistry;
use App\Security\Authorization\Loader\FileAuthorizationLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

final class FileAuthorizationLoaderTest extends TestCase
{
    private string $projectDir;
    private string $configurationDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/authorization-loader-' . bin2hex(random_bytes(8));
        $this->configurationDir = $this->projectDir . '/config/authorization';

        mkdir($this->configurationDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->configurationDir . '/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->configurationDir);
        rmdir($this->projectDir . '/config');
        rmdir($this->projectDir);
    }

    public function testLoadsResourcesUsingResolvedAudienceAsTheirKey(): void
    {
        $this->writeConfiguration('identity-link.yaml', <<<'YAML'
resources:
  '%jwt_url%':
    scopes:
      users.read:
        description: View users
YAML);

        $loader = new FileAuthorizationLoader(
            $this->projectDir,
            new ParameterBag(['jwt_url' => 'identity-link']),
        );

        $this->assertEquals(
            new AuthorizationRegistry([
                'identity-link' => [
                    'scopes' => [
                        'users.read' => ['description' => 'View users'],
                    ],
                ],
            ]),
            $loader->load(),
        );
    }

    public function testLoadsYamlAndYmlFilesInLexicalOrderAndMergesTheirContents(): void
    {
        $this->writeConfiguration('20-support.yml', <<<'YAML'
resources:
  identity-link:
    scopes:
      users.read:
        description: Updated description
      users.write:
        description: Update users
YAML);
        $this->writeConfiguration('10-base.yaml', <<<'YAML'
resources:
  identity-link:
    scopes:
      users.read:
        description: Original description
YAML);

        $this->assertEquals(
            new AuthorizationRegistry([
                'identity-link' => [
                    'scopes' => [
                        'users.read' => ['description' => 'Updated description'],
                        'users.write' => ['description' => 'Update users'],
                    ],
                ],
            ]),
            (new FileAuthorizationLoader($this->projectDir))->load(),
        );
    }

    public function testReturnsAnEmptyRegistryWhenThereAreNoConfigurationFiles(): void
    {
        $this->assertEquals(
            new AuthorizationRegistry(),
            (new FileAuthorizationLoader($this->projectDir))->load(),
        );
    }

    public function testIgnoresAnEmptyConfigurationFile(): void
    {
        $this->writeConfiguration('empty.yaml', '');

        $this->assertEquals(
            new AuthorizationRegistry(),
            (new FileAuthorizationLoader($this->projectDir))->load(),
        );
    }

    public function testRejectsConfigurationThatIsNotAMapping(): void
    {
        $this->writeConfiguration('invalid.yaml', '- resources');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a mapping');

        (new FileAuthorizationLoader($this->projectDir))->load();
    }

    public function testRejectsResourcesThatAreNotAMapping(): void
    {
        $this->writeConfiguration('invalid.yaml', <<<'YAML'
resources: invalid
YAML);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"resources" entry');

        (new FileAuthorizationLoader($this->projectDir))->load();
    }

    private function writeConfiguration(string $file, string $contents): void
    {
        file_put_contents($this->configurationDir . '/' . $file, $contents);
    }
}
