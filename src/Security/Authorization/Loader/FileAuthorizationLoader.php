<?php
declare(strict_types=1);

namespace App\Security\Authorization\Loader;

use App\Security\Authorization\AuthorizationRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Yaml\Yaml;

final class FileAuthorizationLoader implements AuthorizationLoaderInterface
{
    private const CONFIG_DIRECTORY = 'config/authorization';

    public function __construct(
        private readonly string $projectDir,
        private readonly ?ParameterBagInterface $parameterBag = null,
    ) {
    }

    public function load(): AuthorizationRegistry
    {
        $resources = [];

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

            // Files are loaded in lexical order; a later file may deliberately
            // refine a resource declared by an earlier one.
            $resources = array_replace_recursive($resources, $fileResources);
        }

        if (null !== $this->parameterBag) {
            $resources = $this->parameterBag->resolveValue($resources);
        }

        return new AuthorizationRegistry($resources);
    }

    /** @return list<string> */
    private function configurationFiles(): array
    {
        $directory = rtrim($this->projectDir, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . self::CONFIG_DIRECTORY;

        $files = array_merge(
            glob($directory . DIRECTORY_SEPARATOR . '*.yaml') ?: [],
            glob($directory . DIRECTORY_SEPARATOR . '*.yml') ?: [],
        );

        sort($files, SORT_STRING);

        return $files;
    }

    private function isMapping(mixed $value): bool
    {
        return is_array($value) && ([] === $value || !array_is_list($value));
    }
}
