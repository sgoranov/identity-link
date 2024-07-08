<?php

namespace App\Service\Api;

interface TwoFaConnectorInterface
{
    public function initiateAuthenticationRequest(string $userIdentifier): ?string;
    public function validateAuthenticationRequest(string $id): bool;
}