<?php
declare(strict_types=1);

namespace App\Api\IdentityLink\Response;

use App\Api\Contract\UserResponseInterface;

class DbUserResponse implements UserResponseInterface
{
    private string $id;

    private array $claims;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getClaims(): array
    {
        return $this->claims;
    }

    public function setClaims(array $claims): void
    {
        $this->claims = $claims;
    }
}