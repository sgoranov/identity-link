<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\AuthRequestRepository;
use App\Security\LoginStateEnum;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuthRequestRepository::class)]
#[ORM\Table(name: 'auth_request')]
#[ORM\Index(columns: ['expires_at'], name: 'idx_auth_request_expires_at')]
#[ORM\Index(columns: ['consumed'], name: 'idx_auth_request_consumed')]
class AuthRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\CustomIdGenerator(class: "doctrine.uuid_generator")]
    private ?string $id = null;

    #[ORM\Column(type: 'string', length: 191)]
    private string $clientId;

    #[ORM\Column(type: 'json')]
    private array $scopes = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private array $queryParams = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $consumed = false;

    #[ORM\Column(type: 'string', length: 50, nullable: true, enumType: LoginStateEnum::class)]
    private ?LoginStateEnum $loginState = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $userIdentifier = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $consentApproved = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = (new \DateTimeImmutable())->add(new \DateInterval('PT15M'));
        $this->consumed = false;
    }

    public function getId(): ?string
    {
        return $this->id;

    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function setClientId(string $clientId): void
    {
        $this->clientId = $clientId;
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function setScopes(array $scopes): void
    {
        $this->scopes = array_map(
            static fn($scope) => is_object($scope) && method_exists($scope, 'getIdentifier')
                ? $scope->getIdentifier()
                : (string) $scope,
            $scopes
        );
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function setQueryParams(array $queryParams): void
    {
        $this->queryParams = $queryParams;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isConsumed(): bool
    {
        return $this->consumed;
    }

    public function consume(): void
    {
        $this->consumed = true;
    }

    public function getLoginState(): ?LoginStateEnum
    {
        return $this->loginState;
    }

    public function setLoginState(?LoginStateEnum $loginState): void
    {
        $this->loginState = $loginState;
    }

    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    public function setUserIdentifier(?string $userIdentifier): void
    {
        $this->userIdentifier = $userIdentifier;
    }

    public function getConsentApproved(): ?bool
    {
        return $this->consentApproved;
    }

    public function setConsentApproved(?bool $consentApproved): void
    {
        $this->consentApproved = $consentApproved;
    }
}