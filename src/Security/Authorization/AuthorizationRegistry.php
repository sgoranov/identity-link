<?php
declare(strict_types=1);

namespace App\Security\Authorization;

class AuthorizationRegistry
{
    /**
     * @param array<string, array{scopes?: array<string, mixed>, aliases?: array<string, mixed>}> $resources
     *        Scopes and aliases declared by each audience, keyed by audience.
     * @param array{scopes?: array<string, mixed>, aliases?: array<string, mixed>} $openIdConnectResources
     *        Scopes and aliases declared in the openid_connect_resources section of config/authorization.yaml.
     */
    public function __construct(
        private readonly array $resources = [],
        private readonly array $openIdConnectResources = [],
    ) {
        $this->validateAudiences();
        $this->validateAliases();
    }

    /**
     * Checks whether the identifier is declared as a scope or an alias in the openid_connect_resources section or by
     * any audience. It does not tell whether the identifier is available for a particular audience.
     */
    public function containsScopeOrAlias(string $identifier): bool
    {
        $sections = [
            'openid_connect_resources' => $this->openIdConnectResources,
            ...$this->resources,
        ];

        foreach ($sections as $section) {
            if (isset($section['scopes'][$identifier]) || isset($section['aliases'][$identifier])) {
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
            throw new UnknownAudienceException(sprintf('Unknown audience "%s".', $audience));
        }

        return [
            'scopes' => $resource['scopes'] ?? [],
            'aliases' => $resource['aliases'] ?? [],
        ];
    }

    /**
     * @return array{scopes: array<string, mixed>, aliases: array<string, mixed>}
     */
    public function getOpenIdConnectResources(): array
    {
        return [
            'scopes' => $this->openIdConnectResources['scopes'] ?? [],
            'aliases' => $this->openIdConnectResources['aliases'] ?? [],
        ];
    }

    /**
     * Returns the names of the OpenID Connect default scopes, without their aliases.
     *
     * @return list<string>
     */
    public function getOpenIdConnectResourcesScopes(): array
    {
        return array_keys($this->getOpenIdConnectResources()['scopes']);
    }

    /**
     * Expands aliases and drops identifiers the audience does not know. Every audience knows the OpenID Connect default
     * scopes and aliases. This only resolves names: whether a client or user receives a scope is decided by the
     * caller.
     *
     * @param list<string> $identifiers
     * @return list<string>
     */
    public function expandScopes(string $audience, array $identifiers): array
    {
        $resource = $this->resources[$audience] ?? null;
        if (!is_array($resource)) {
            throw new UnknownAudienceException(sprintf('Unknown audience "%s".', $audience));
        }

        // Every audience knows the OpenID Connect default scopes and aliases. The loader rejects an audience that
        // redeclares one, so the two never overlap.
        $scopes = ($this->openIdConnectResources['scopes'] ?? []) + ($resource['scopes'] ?? []);
        $aliases = ($this->openIdConnectResources['aliases'] ?? []) + ($resource['aliases'] ?? []);

        $expanded = [];

        foreach ($identifiers as $identifier) {
            if (isset($aliases[$identifier])) {
                foreach ($aliases[$identifier]['scopes'] as $scope) {
                    $expanded[$scope] = $scope;
                }

                continue;
            }

            if (isset($scopes[$identifier])) {
                $expanded[$identifier] = $identifier;
            }
        }

        return array_values($expanded);
    }

    /**
     * The audiences are already resolved here, so an audience parameter such as %jwt_url% that resolves to an empty
     * value is rejected too.
     */
    private function validateAudiences(): void
    {
        foreach (array_keys($this->resources) as $audience) {
            if ('' === trim((string) $audience)) {
                throw new \InvalidArgumentException(sprintf('Audience "%s" must not be empty.', $audience));
            }
        }
    }

    private function validateAliases(): void
    {
        $openIdConnectScopes = $this->openIdConnectResources['scopes'] ?? [];

        // OpenID Connect default aliases may reference only OpenID Connect default scopes.
        $this->validateAliasDefinitions(
            $this->openIdConnectResources['aliases'] ?? [],
            $openIdConnectScopes,
            '"openid_connect_resources"',
        );

        foreach ($this->resources as $audience => $resource) {
            // An audience alias may reference the audience's own scopes and the OpenID Connect default scopes.
            $this->validateAliasDefinitions(
                $resource['aliases'] ?? [],
                $openIdConnectScopes + ($resource['scopes'] ?? []),
                sprintf('audience "%s"', $audience),
            );
        }
    }

    /**
     * @param array<array-key, mixed> $aliases
     * @param array<array-key, mixed> $scopes Scopes the aliases may reference, keyed by name.
     * @param string $owner Where the aliases are declared, as shown in error messages.
     */
    private function validateAliasDefinitions(array $aliases, array $scopes, string $owner): void
    {
        foreach ($aliases as $alias => $definition) {
            if (
                !is_array($definition)
                || !is_string($definition['description'] ?? null)
                || !is_array($definition['scopes'] ?? null)
            ) {
                throw new \InvalidArgumentException(sprintf(
                    'Scope alias "%s" for %s must define a description and scopes.',
                    $alias,
                    $owner,
                ));
            }

            foreach ($definition['scopes'] as $scope) {
                if (!is_string($scope) || !isset($scopes[$scope])) {
                    throw new \InvalidArgumentException(sprintf(
                        'Scope alias "%s" for %s references undefined scope "%s".',
                        $alias,
                        $owner,
                        is_scalar($scope) ? (string) $scope : get_debug_type($scope),
                    ));
                }
            }
        }
    }

}
