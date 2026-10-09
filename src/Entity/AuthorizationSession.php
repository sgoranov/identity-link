<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\AuthorizationSessionRepository;
use App\Security\AuthorizationStateEnum;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuthorizationSessionRepository::class)]
#[ORM\Table(name: 'authorization_session')]
#[ORM\Index(columns: ['expires_at'], name: 'idx_authorization_session_expires_at')]
#[ORM\Index(columns: ['consumed'], name: 'idx_authorization_session_consumed')]
class AuthorizationSession
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

    #[ORM\Column(type: 'string', length: 50, nullable: true, enumType: AuthorizationStateEnum::class)]
    private ?AuthorizationStateEnum $authorizationState = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $userIdentifier = null;

    #[ORM\Column(type: 'string', length: 64)]
    private string $browserBindingHash;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $twoFaRequestId = null;

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

    public function getAuthorizationState(): ?AuthorizationStateEnum
    {
        return $this->authorizationState;
    }

    public function setAuthorizationState(?AuthorizationStateEnum $authorizationState): void
    {
        $this->authorizationState = $authorizationState;
    }

    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    public function setUserIdentifier(?string $userIdentifier): void
    {
        $this->userIdentifier = $userIdentifier;
    }

    public function getBrowserBindingHash(): string
    {
        return $this->browserBindingHash;
    }

    public function setBrowserBindingHash(string $browserBindingHash): void
    {
        $this->browserBindingHash = $browserBindingHash;
    }

    public function getTwoFaRequestId(): ?string
    {
        return $this->twoFaRequestId;
    }

    public function setTwoFaRequestId(?string $twoFaRequestId): void
    {
        $this->twoFaRequestId = $twoFaRequestId;
    }
}
