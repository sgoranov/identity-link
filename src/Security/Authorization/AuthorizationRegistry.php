<?php
declare(strict_types=1);

namespace App\Security\Authorization;

class AuthorizationRegistry
{
    public function __construct(
        private readonly array $resources = [],
    ) {
        $this->validateAliases();
    }

    public function containsScopeOrAlias(string $identifier): bool
    {
        foreach ($this->resources as $resource) {
            if (isset($resource['scopes'][$identifier]) || isset($resource['aliases'][$identifier])) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function getAudiences(): array
    {
        return array_keys($this->resources);
    }

    /**
     * @return array{scopes: array<string, mixed>, aliases: array<string, mixed>}
     */
    public function getScopesAndAliases(string $audience): array
    {
        $resource = $this->resources[$audience] ?? null;
        if (!is_array($resource)) {
            throw new \InvalidArgumentException(sprintf('Unknown audience "%s".', $audience));
        }

        return [
            'scopes' => $resource['scopes'] ?? [],
            'aliases' => $resource['aliases'] ?? [],
        ];
    }

    /**
     * @param list<string> $identifiers
     * @return list<string>
     */
    public function expandScopes(string $audience, array $identifiers): array
    {
        $resource = $this->resources[$audience] ?? null;
        if (!is_array($resource)) {
            throw new \InvalidArgumentException(sprintf('Unknown audience "%s".', $audience));
        }

        $expanded = [];

        foreach ($identifiers as $identifier) {
            if (isset($resource['aliases'][$identifier])) {
                foreach ($resource['aliases'][$identifier]['scopes'] as $scope) {
                    $expanded[$scope] = $scope;
                }

                continue;
            }

            if (isset($resource['scopes'][$identifier])) {
                $expanded[$identifier] = $identifier;
            }
        }

        return array_values($expanded);
    }

    private function validateAliases(): void
    {
        foreach ($this->resources as $audience => $resource) {
            $scopes = $resource['scopes'] ?? [];

            foreach ($resource['aliases'] ?? [] as $alias => $definition) {
                if (
                    !is_array($definition)
                    || !is_string($definition['description'] ?? null)
                    || !is_array($definition['scopes'] ?? null)
                ) {
                    throw new \InvalidArgumentException(sprintf(
                        'Scope alias "%s" for audience "%s" must define a description and scopes.',
                        $alias,
                        $audience,
                    ));
                }

                foreach ($definition['scopes'] as $scope) {
                    if (!is_string($scope) || !isset($scopes[$scope])) {
                        throw new \InvalidArgumentException(sprintf(
                            'Scope alias "%s" for audience "%s" references undefined scope "%s".',
                            $alias,
                            $audience,
                            is_scalar($scope) ? (string) $scope : get_debug_type($scope),
                        ));
                    }
                }
            }
        }
    }

}
