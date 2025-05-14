<?php
declare(strict_types=1);

namespace App\Api\IdentityLink\Response;

use DateTime;

class AuthResponse
{
    private string $id;
    private string $identifier;
    private DateTime $created;
    private DateTime $expired;
    private ?DateTime $authenticated = null;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }

    public function setCreated(DateTime $created): void
    {
        $this->created = $created;
    }

    public function getExpired(): DateTime
    {
        return $this->expired;
    }

    public function setExpired(DateTime $expired): void
    {
        $this->expired = $expired;
    }

    public function getAuthenticated(): ?DateTime
    {
        return $this->authenticated;
    }

    public function setAuthenticated(?DateTime $authenticated): void
    {
        $this->authenticated = $authenticated;
    }
}