<?php
declare(strict_types=1);

namespace App\Model\OAuth2;

use DateTimeImmutable;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;

class AccessTokenModel implements AccessTokenEntityInterface
{
    use AccessTokenTrait;

    private string $identifier;
    private DateTimeImmutable $dateTime;
    private ?string $userIdentifier;
    private bool $isRevoked = false;

    /**
     * @var ScopeModel[]
     */
    private array $scopes;
    private ClientEntityInterface $client;

    private ?array $groups = null;

    public function __toString(): string
    {
        $this->initJwtConfiguration();

        $builder = $this->jwtConfiguration->builder();

        $builder = $builder->permittedFor($this->getClient()->getIdentifier());
        $builder = $builder->identifiedBy($this->getIdentifier());
        $builder = $builder->issuedAt(new DateTimeImmutable());
        $builder = $builder->canOnlyBeUsedAfter(new DateTimeImmutable());
        $builder = $builder->expiresAt($this->getExpiryDateTime());
        $builder = $builder->relatedTo((string) $this->getUserIdentifier());
        $builder = $builder->withClaim('scopes', $this->getScopes());

        if ($this->groups !== null) {
            $builder = $builder->withClaim('groups', $this->groups);
        }

        $token = $builder->getToken($this->jwtConfiguration->signer(), $this->jwtConfiguration->signingKey());

        return $token->toString();
    }

    public function setGroups(?array $groups): void
    {
        $this->groups = $groups;
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

    public function getUserIdentifier(): ?string
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

    public function setScopes(array $scopes): void
    {
        $this->scopes = $scopes;
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