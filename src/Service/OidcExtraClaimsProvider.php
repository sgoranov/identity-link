<?php
declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class OidcExtraClaimsProvider implements \ArrayAccess, \IteratorAggregate, \Countable
{
    private array $claims;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $raw = $parameterBag->get('oidc_extra_claims');

        if (!empty($raw)) {
            try {
                $this->claims = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new \InvalidArgumentException(
                    sprintf('Invalid JSON in oidc_extra_claims: %s', $e->getMessage()));
            }
        } else {
            $this->claims = [];
        }
    }

    public function getClaims(): array
    {
        return $this->claims;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->claims[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->claims[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->claims[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->claims[$offset]);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->claims);
    }

    public function count(): int
    {
        return count($this->claims);
    }
}