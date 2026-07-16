<?php

namespace App\Api\Contract;

interface ClientResponseInterface
{
    public function getId(): string;
    public function getName(): string;
    public function getRedirectUri(): array|string;
    public function isPublic(): bool;
    public function getScopes(): array;
    public function getGrantTypes(): array;
    public function isConsentRequired(): bool;
    public function getApplicationUrl(): ?string;
    public function getTermsOfServiceUrl(): ?string;
    public function getPrivacyPolicyUrl(): ?string;
    public function getLogoUrl(): ?string;
}
