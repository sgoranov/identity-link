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
        if (file_exists($this->projectDir . '/config/authorization.yaml')) {
            unlink($this->projectDir . '/config/authorization.yaml');
        }

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

    public function testLoadsOpenIdConnectResourcesScopesFromTheBuiltInConfiguration(): void
    {
        $this->writeDefaultConfiguration(<<<'YAML'
openid_connect_resources:
  scopes:
    openid:
      description: Sign in
    email:
      description: Read the email address
  aliases:
    oidc.default:
      description: Standard identity scopes
      scopes: [openid, email]
resources:
  '%jwt_url%': {}
YAML);

        $loader = new FileAuthorizationLoader(
            $this->projectDir,
            new ParameterBag(['jwt_url' => 'identity-link']),
        );

        $this->assertEquals(
            new AuthorizationRegistry(
                ['identity-link' => []],
                [
                    'scopes' => [
                        'openid' => ['description' => 'Sign in'],
                        'email' => ['description' => 'Read the email address'],
                    ],
                    'aliases' => [
                        'oidc.default' => [
                            'description' => 'Standard identity scopes',
                            'scopes' => ['openid', 'email'],
                        ],
                    ],
                ],
            ),
            $loader->load(),
        );
    }

    /**
     * @dataProvider openIdConnectResourcesOutsideTheBuiltInConfigurationProvider
     */
    public function testRejectsOpenIdConnectResourcesOutsideTheBuiltInConfiguration(string $openIdConnectResources): void
    {
        $this->writeDefaultConfiguration(<<<'YAML'
openid_connect_resources:
  scopes:
    openid:
      description: Sign in
YAML);
        $this->writeConfiguration('custom.yaml', $openIdConnectResources);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'OpenID Connect default scopes can only be declared in "config/authorization.yaml"; remove the "openid_connect_resources" entry from',
        );

        (new FileAuthorizationLoader($this->projectDir))->load();
    }

    /** @return array<string, array{string}> */
    public static function openIdConnectResourcesOutsideTheBuiltInConfigurationProvider(): array
    {
        return [
            'redefined scope' => [<<<'YAML'
openid_connect_resources:
  scopes:
    openid:
      description: Changed
YAML],
            'added scope' => [<<<'YAML'
openid_connect_resources:
  scopes:
    phone:
      description: Read the phone number
YAML],
            'empty entry' => ['openid_connect_resources: {}'],
        ];
    }

    public function testRejectsOpenIdConnectResourcesThatIsNotAMapping(): void
    {
        $this->writeDefaultConfiguration(<<<'YAML'
openid_connect_resources: invalid
YAML);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"openid_connect_resources" entry');

        (new FileAuthorizationLoader($this->projectDir))->load();
    }

    /**
     * @dataProvider openIdConnectRedeclarationProvider
     */
    public function testRejectsAudienceRedeclaringOpenIdConnectDefaultScopeOrAlias(
        string $resources,
        string $identifier,
    ): void {
        $this->writeDefaultConfiguration(<<<'YAML'
openid_connect_resources:
  scopes:
    openid:
      description: Sign in
  aliases:
    oidc.default:
      description: Standard identity scopes
      scopes: [openid]
YAML);
        $this->writeConfiguration('orders.yaml', $resources);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'Audience "https://orders.example" must not redeclare OpenID Connect default scope or alias "%s"; remove it from "%s".',
            $identifier,
            $this->configurationDir . '/orders.yaml',
        ));

        (new FileAuthorizationLoader($this->projectDir))->load();
    }

    /** @return array<string, array{string, string}> */
    public static function openIdConnectRedeclarationProvider(): array
    {
        return [
            'scope' => [<<<'YAML'
resources:
  'https://orders.example':
    scopes:
      openid:
        description: Sign in to orders
YAML, 'openid'],
            'alias' => [<<<'YAML'
resources:
  'https://orders.example':
    scopes:
      orders.read:
        description: View orders
    aliases:
      oidc.default:
        description: Read orders
        scopes: [orders.read]
YAML, 'oidc.default'],
            'scope named like an alias' => [<<<'YAML'
resources:
  'https://orders.example':
    scopes:
      oidc.default:
        description: View orders
YAML, 'oidc.default'],
            'alias named like a scope' => [<<<'YAML'
resources:
  'https://orders.example':
    scopes:
      orders.read:
        description: View orders
    aliases:
      openid:
        description: Read orders
        scopes: [orders.read]
YAML, 'openid'],
        ];
    }

    public function testRejectsAudienceRedeclaringOpenIdConnectDefaultScopeInTheBuiltInConfiguration(): void
    {
        $this->writeDefaultConfiguration(<<<'YAML'
resources:
  '%jwt_url%':
    scopes:
      email:
        description: Send email on the user's behalf
openid_connect_resources:
  scopes:
    email:
      description: Read the email address
YAML);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'Audience "%%jwt_url%%" must not redeclare OpenID Connect default scope or alias "email"; remove it from "%s".',
            $this->projectDir . '/config/authorization.yaml',
        ));

        (new FileAuthorizationLoader($this->projectDir))->load();
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

    private function writeDefaultConfiguration(string $contents): void
    {
        file_put_contents($this->projectDir . '/config/authorization.yaml', $contents);
    }

    private function writeConfiguration(string $file, string $contents): void
    {
        file_put_contents($this->configurationDir . '/' . $file, $contents);
    }
}
