<?php
declare(strict_types=1);

namespace App\Api\IdentityLink\Response;

use App\Api\Contract\UserResponseInterface;

class DbUserResponse implements UserResponseInterface
{
    private string $id;
    private string $firstName;
    private string $lastName;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getDisplayName(): string
    {
        return sprintf('%s %s', $this->firstName, $this->lastName);
    }
}