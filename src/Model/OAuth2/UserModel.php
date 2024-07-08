<?php
declare(strict_types=1);

namespace App\Model\OAuth2;

use League\OAuth2\Server\Entities\UserEntityInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserModel implements UserEntityInterface, UserInterface
{

    private string $identifier;

    private array $roles = [];

    private bool $isTwoFaEnabled = false;

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->getIdentifier();
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    public function isTwoFaEnabled(): bool
    {
        return $this->isTwoFaEnabled;
    }

    public function setIsTwoFaEnabled(bool $isTwoFaEnabled): void
    {
        $this->isTwoFaEnabled = $isTwoFaEnabled;
    }
}