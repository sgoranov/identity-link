<?php
declare(strict_types=1);

namespace App\LeagueOAuth2\Entity;

use App\LeagueOAuth2\CryptKey;
use DateTimeImmutable;
use Lcobucci\JWT\Token;
use League\OAuth2\Server\CryptKeyInterface;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface as LeagueClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;

class AccessTokenEntity implements AccessTokenEntityInterface
{
    use AccessTokenTrait { setPrivateKey as setPrivateKeyInTrait ; }

    private string $identifier;
    private DateTimeImmutable $dateTime;
    private ?string $userIdentifier;
    private bool $isRevoked = false;
    private CryptKeyInterface $privateKey;
    private string $issuer;

    /**
     * @var ScopeEntity[]
     */
    private array $scopes;
    private LeagueClientEntityInterface $client;

    private ?array $groups = null;

    private function convertToJWT(): Token
    {
        $this->initJwtConfiguration();

        $client = $this->getClient();
        if (!$client instanceof ClientEntityInterface) {
            throw new \LogicException(sprintf(
                'Client entity "%s" must provide an audience.',
                $client::class,
            ));
        }

        $builder = $this->jwtConfiguration->builder();

        $builder = $builder->withHeader('kid', $this->privateKey->getId());
        $builder = $builder->permittedFor($client->getAudience());
        $builder = $builder->issuedBy($this->issuer);
        $builder = $builder->identifiedBy($this->getIdentifier());
        $builder = $builder->issuedAt(new DateTimeImmutable());
        $builder = $builder->canOnlyBeUsedAfter(new DateTimeImmutable());
        $builder = $builder->expiresAt($this->getExpiryDateTime());
        $builder = $builder->relatedTo((string) $this->getUserIdentifier());
        $builder = $builder->withClaim('oid', (string) $this->getUserIdentifier());
        $builder = $builder->withClaim('client_id', $client->getIdentifier());
        $builder = $builder->withClaim('scope', implode(' ', array_map(
            static fn (ScopeEntityInterface $scope): string => $scope->getIdentifier(),
            $this->getScopes(),
        )));

        if ($this->groups !== null) {
            $builder = $builder->withClaim('groups', $this->groups);
        }

        return $builder->getToken($this->jwtConfiguration->signer(), $this->jwtConfiguration->signingKey());
    }

    public function setPrivateKey(
        #[\SensitiveParameter]
        CryptKeyInterface $privateKey
    ): void {
        $this->setPrivateKeyInTrait($privateKey);
        $this->privateKey = $privateKey;
    }

    public function setGroups(?array $groups): void
    {
        $this->groups = $groups;
    }

    public function setIssuer(string $issuer): void
    {
        $this->issuer = $issuer;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier($identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getExpiryDateTime(): DateTimeImmutable
    {
        return $this->dateTime;
    }

    public function setExpiryDateTime(DateTimeImmutable $dateTime): void
    {
        $this->dateTime = $dateTime;
    }

    public function setUserIdentifier($identifier): void
    {
        $this->userIdentifier = $identifier;
    }

    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    public function getClient(): LeagueClientEntityInterface
    {
        return $this->client;
    }

    public function setClient(LeagueClientEntityInterface $client): void
    {
        $this->client = $client;
    }

    public function addScope(ScopeEntityInterface $scope): void
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
