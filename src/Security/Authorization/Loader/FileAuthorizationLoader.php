<?php
declare(strict_types=1);

namespace App\Security\Authorization\Loader;

use App\Security\Authorization\AuthorizationRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Yaml\Yaml;

final class FileAuthorizationLoader implements AuthorizationLoaderInterface
{
    private const CONFIG_DIRECTORY = 'config/authorization';
    private const CONFIG_FILE = 'config/authorization.yaml';

    /**
     * @param string|null $configurationDirectory Directory of the additional configuration files; defaults to
     *        config/authorization in the project directory.
     */
    public function __construct(
        private readonly string $projectDir,
        private readonly ?ParameterBagInterface $parameterBag = null,
        private readonly ?string $configurationDirectory = null,
    ) {
    }

    public function load(): AuthorizationRegistry
    {
        $resources = [];
        $openIdConnectResources = [];

        foreach ($this->configurationFiles() as $file) {
            $configuration = Yaml::parseFile($file);

            if (null === $configuration) {
                continue;
            }

            if (!$this->isMapping($configuration)) {
                throw new \InvalidArgumentException(sprintf(
                    'Authorization configuration in "%s" must be a mapping.',
                    $file,
                ));
            }

            $fileResources = $configuration['resources'] ?? [];

            if (!$this->isMapping($fileResources)) {
                throw new \InvalidArgumentException(sprintf(
                    'The "resources" entry in "%s" must be a mapping.',
                    $file,
                ));
            }

            foreach ($fileResources as $audience => $resource) {
                // An audience without a value would be listed as an audience but unknown when issuing tokens, and
                // would replace an audience declared by an earlier file when merged.
                if (!$this->isMapping($resource)) {
                    throw new \InvalidArgumentException(sprintf(
                        'Audience "%s" in "%s" must be a mapping; write {} for an audience that declares no scopes or aliases.',
                        $audience,
                        $file,
                    ));
                }
            }

            // Files are loaded in lexical order; a later file may deliberately
            // refine a resource declared by an earlier one.
            $resources = array_replace_recursive($resources, $fileResources);

            if (array_key_exists('openid_connect_resources', $configuration)) {
                // The OpenID Connect default scopes are shared by every audience, so only the built-in
                // configuration may declare them; no other file may add or redefine one.
                if ($file !== $this->defaultConfigurationFile()) {
                    throw new \InvalidArgumentException(sprintf(
                        'OpenID Connect default scopes can only be declared in "%s"; remove the "openid_connect_resources" entry from "%s".',
                        self::CONFIG_FILE,
                        $file,
                    ));
                }

                $openIdConnectResources = $configuration['openid_connect_resources'];

                if (!$this->isMapping($openIdConnectResources)) {
                    throw new \InvalidArgumentException(sprintf(
                        'The "openid_connect_resources" entry in "%s" must be a mapping.',
                        $file,
                    ));
                }

                foreach (['scopes', 'aliases'] as $section) {
                    // A list would key the scopes by position, leaving every OpenID Connect scope unknown.
                    if (
                        array_key_exists($section, $openIdConnectResources)
                        && !$this->isMapping($openIdConnectResources[$section])
                    ) {
                        throw new \InvalidArgumentException(sprintf(
                            'The "openid_connect_resources.%s" entry in "%s" must be a mapping.',
                            $section,
                            $file,
                        ));
                    }
                }
            }

            // The built-in configuration is loaded first, so the OpenID Connect default scopes are known here for
            // every file, including the built-in one.
            $this->rejectOpenIdConnectRedeclarations($fileResources, $openIdConnectResources, $file);
        }

        if (null !== $this->parameterBag) {
            $resources = $this->parameterBag->resolveValue($resources);
            $openIdConnectResources = $this->parameterBag->resolveValue($openIdConnectResources);
        }

        return new AuthorizationRegistry($resources, $openIdConnectResources);
    }

    /**
     * An audience must not declare a scope or alias named like an OpenID Connect default scope or alias. The identity
     * scopes are exempt from user scope assignment by name, so a redeclared one would grant a resource permission to
     * users who were never assigned it.
     *
     * @param array<array-key, mixed> $fileResources
     * @param array<array-key, mixed> $openIdConnectResources
     */
    private function rejectOpenIdConnectRedeclarations(
        array $fileResources,
        array $openIdConnectResources,
        string $file,
    ): void {
        $reservedIdentifiers = [];

        foreach (['scopes', 'aliases'] as $section) {
            if (is_array($openIdConnectResources[$section] ?? null)) {
                $reservedIdentifiers += $openIdConnectResources[$section];
            }
        }

        foreach ($fileResources as $audience => $resource) {
            foreach (['scopes', 'aliases'] as $section) {
                if (!is_array($resource[$section] ?? null)) {
                    continue;
                }

                foreach (array_keys($resource[$section]) as $identifier) {
                    if (array_key_exists($identifier, $reservedIdentifiers)) {
                        throw new \InvalidArgumentException(sprintf(
                            'Audience "%s" must not redeclare OpenID Connect default scope or alias "%s"; remove it from "%s".',
                            $audience,
                            $identifier,
                            $file,
                        ));
                    }
                }
            }
        }
    }

    private function defaultConfigurationFile(): string
    {
        return rtrim($this->projectDir, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . self::CONFIG_FILE;
    }

    /** @return list<string> */
    private function configurationFiles(): array
    {
        $defaultConfig = $this->defaultConfigurationFile();

        $directory = rtrim(
            $this->configurationDirectory
                ?? rtrim($this->projectDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::CONFIG_DIRECTORY,
            DIRECTORY_SEPARATOR,
        );

        $files = array_merge(
            glob($directory . DIRECTORY_SEPARATOR . '*.yaml') ?: [],
            glob($directory . DIRECTORY_SEPARATOR . '*.yml') ?: [],
        );

        sort($files, SORT_STRING);

        return file_exists($defaultConfig) ? [$defaultConfig, ...$files] : $files;
    }

    private function isMapping(mixed $value): bool
    {
        return is_array($value) && ([] === $value || !array_is_list($value));
    }
}
