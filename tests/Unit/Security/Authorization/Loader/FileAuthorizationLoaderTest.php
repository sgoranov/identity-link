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

    public function testLoadsAdditionalFilesFromTheConfiguredDirectoryInsteadOfConfigAuthorization(): void
    {
        $this->writeConfiguration('ignored.yaml', "resources:\n  'https://ignored.example': {}\n");
        $directory = $this->projectDir . '/fixtures';
        mkdir($directory);
        file_put_contents($directory . '/app.yaml', "resources:\n  'https://app.example': {}\n");

        try {
            $this->assertEquals(
                new AuthorizationRegistry(['https://app.example' => []]),
                (new FileAuthorizationLoader($this->projectDir, configurationDirectory: $directory))->load(),
            );
        } finally {
            unlink($directory . '/app.yaml');
            rmdir($directory);
        }
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

    /**
     * @dataProvider emptyJwtUrlProvider
     */
    public function testRejectsAudienceParameterResolvingToAnEmptyValue(string $jwtUrl): void
    {
        $this->writeConfiguration('app.yaml', "resources:\n  '%jwt_url%': {}\n");

        $loader = new FileAuthorizationLoader($this->projectDir, new ParameterBag(['jwt_url' => $jwtUrl]));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Audience "%s" must not be empty.', $jwtUrl));

        $loader->load();
    }

    /** @return array<string, array{string}> */
    public static function emptyJwtUrlProvider(): array
    {
        return [
            'empty' => [''],
            'whitespace' => ['   '],
        ];
    }

    /**
     * @dataProvider audienceThatIsNotAMappingProvider
     */
    public function testRejectsAudienceThatIsNotAMapping(string $resources): void
    {
        $this->writeConfiguration('app.yaml', $resources);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'Audience "https://app.example" in "%s" must be a mapping; write {} for an audience that declares no scopes or aliases.',
            $this->configurationDir . '/app.yaml',
        ));

        (new FileAuthorizationLoader($this->projectDir))->load();
    }

    /** @return array<string, array{string}> */
    public static function audienceThatIsNotAMappingProvider(): array
    {
        return [
            'no value' => ["resources:\n  'https://app.example':\n"],
            'null' => ["resources:\n  'https://app.example': ~\n"],
            'string' => ["resources:\n  'https://app.example': invalid\n"],
            'list' => ["resources:\n  'https://app.example': [users.read]\n"],
        ];
    }

    public function testRejectsAudienceWithoutValueThatWouldReplaceAnEarlierDeclaration(): void
    {
        $this->writeConfiguration('10-base.yaml', <<<'YAML'
resources:
  'https://app.example':
    scopes:
      users.read:
        description: View users
YAML);
        $this->writeConfiguration('20-app.yaml', "resources:\n  'https://app.example':\n");

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'Audience "https://app.example" in "%s" must be a mapping',
            $this->configurationDir . '/20-app.yaml',
        ));

        (new FileAuthorizationLoader($this->projectDir))->load();
    }

    /**
     * @dataProvider openIdConnectResourcesSectionThatIsNotAMappingProvider
     */
    public function testRejectsOpenIdConnectResourcesSectionThatIsNotAMapping(
        string $openIdConnectResources,
        string $section,
    ): void {
        $this->writeDefaultConfiguration($openIdConnectResources);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'The "openid_connect_resources.%s" entry in "%s" must be a mapping.',
            $section,
            $this->projectDir . '/config/authorization.yaml',
        ));

        (new FileAuthorizationLoader($this->projectDir))->load();
    }

    /** @return array<string, array{string, string}> */
    public static function openIdConnectResourcesSectionThatIsNotAMappingProvider(): array
    {
        return [
            'scopes as a list' => ["openid_connect_resources:\n  scopes: [openid, email]\n", 'scopes'],
            'scopes as a string' => ["openid_connect_resources:\n  scopes: openid\n", 'scopes'],
            'scopes without value' => ["openid_connect_resources:\n  scopes:\n", 'scopes'],
            'aliases as a list' => [<<<'YAML'
openid_connect_resources:
  scopes:
    openid:
      description: Sign in
  aliases: [oidc.default]
YAML, 'aliases'],
        ];
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
