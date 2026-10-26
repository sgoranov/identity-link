<?php
declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface
{
    public function __construct(
        private readonly string $identifier,
        private readonly array $roles = []
    )
    {
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // there is no sensitive data to remove
        // from the User object
    }

    public function getUserIdentifier(): string
    {
        return $this->identifier;
    }
}