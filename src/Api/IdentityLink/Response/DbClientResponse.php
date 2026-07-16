<?php
declare(strict_types=1);

namespace App\Api\IdentityLink\Response;

use App\Api\Contract\ClientResponseInterface;
use Symfony\Component\Serializer\Attribute\SerializedName;

class DbClientResponse implements ClientResponseInterface
{
    private string $id;
    private string $name;
    private array|string $redirectUri = '';

    #[SerializedName('isPublic')]
    private bool $public;
    private array $scopes;
    private array $grantTypes;
    private bool $consentRequired;
    private ?string $applicationUrl = null;
    private ?string $termsOfServiceUrl = null;
    private ?string $privacyPolicyUrl = null;
    private ?string $logoUrl = null;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getRedirectUri(): array|string
    {
        return $this->redirectUri;
    }

    public function setRedirectUri(array|string $redirectUri): void
    {
        $this->redirectUri = $redirectUri;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): void
    {
        $this->public = $public;
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function setScopes(array $scopes): void
    {
        $this->scopes = $scopes;
    }

    public function getGrantTypes(): array
    {
        return $this->grantTypes;
    }

    public function setGrantTypes(array $grantTypes): void
    {
        $this->grantTypes = $grantTypes;
    }

    public function isConsentRequired(): bool
    {
        return $this->consentRequired;
    }

    public function setConsentRequired(bool $consentRequired): void
    {
        $this->consentRequired = $consentRequired;
    }

    public function getApplicationUrl(): ?string
    {
        return $this->applicationUrl;
    }

    public function setApplicationUrl(?string $applicationUrl): void
    {
        $this->applicationUrl = $applicationUrl;
    }

    public function getTermsOfServiceUrl(): ?string
    {
        return $this->termsOfServiceUrl;
    }

    public function setTermsOfServiceUrl(?string $termsOfServiceUrl): void
    {
        $this->termsOfServiceUrl = $termsOfServiceUrl;
    }

    public function getPrivacyPolicyUrl(): ?string
    {
        return $this->privacyPolicyUrl;
    }

    public function setPrivacyPolicyUrl(?string $privacyPolicyUrl): void
    {
        $this->privacyPolicyUrl = $privacyPolicyUrl;
    }

    public function getLogoUrl(): ?string
    {
        return $this->logoUrl;
    }

    public function setLogoUrl(?string $logoUrl): void
    {
        $this->logoUrl = $logoUrl;
    }
}
