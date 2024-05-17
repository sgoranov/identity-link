<?php
declare(strict_types=1);

namespace App\Model\OAuth2;

use DateTimeImmutable;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

class AuthCodeModel implements AuthCodeEntityInterface
{
    private string $identifier;
    private ?string $redirectUri = null;
    private string $userIdentifier;
    private array $scopes = [];
    private DateTimeImmutable $dateTime;
    private ClientEntityInterface $client;
    private bool $isRevoked = false;

    public function getRedirectUri(): ?string
    {
        return $this->redirectUri;
    }

    public function setRedirectUri($uri)
    {
        $this->redirectUri = $uri;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;
    }

    public function getExpiryDateTime(): DateTimeImmutable
    {
        return $this->dateTime;
    }

    public function setExpiryDateTime(DateTimeImmutable $dateTime)
    {
        $this->dateTime = $dateTime;
    }

    public function setUserIdentifier($identifier)
    {
        $this->userIdentifier = $identifier;
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    public function getClient(): ClientEntityInterface
    {
        return $this->client;
    }

    public function setClient(ClientEntityInterface $client)
    {
        $this->client = $client;
    }

    public function addScope(ScopeEntityInterface $scope)
    {
        if (!in_array($scope->getIdentifier(), $this->scopes, true)) {
            $this->scopes[] = $scope;
        }
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function isRevoked(): bool
    {
        return $this->isRevoked;
    }

    public function setIsRevoked(bool $isRevoked): void
    {
        $this->isRevoked = $isRevoked;
    }
}