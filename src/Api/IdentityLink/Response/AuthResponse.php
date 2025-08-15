<?php
declare(strict_types=1);

namespace App\Api\IdentityLink\Response;

use App\Api\Contract\TwoFaConnectorResponseInterface;
use DateTime;

class AuthResponse implements TwoFaConnectorResponseInterface
{
    private string $id;
    private string $userId;
    private string $redirectUri;
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

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getRedirectUri(): string
    {
        return $this->redirectUri;
    }

    public function setRedirectUri(string $redirectUri): void
    {
        $this->redirectUri = $redirectUri;
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