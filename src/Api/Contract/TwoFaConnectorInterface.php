<?php

namespace App\Api\Contract;

interface TwoFaConnectorInterface
{
    public function initiateAuthenticationRequest(string $userIdentifier): ?string;
    public function validateAuthenticationRequest(string $id): bool;
    public function isTwoFaEnabled(): bool;
}