<?php

namespace App\Api\Contract;

interface TwoFaConnectorInterface
{
    public function initiateAuthenticationRequest(string $userIdentifier, string $redirectUri): ?string;
    public function validateAuthenticationRequest(string $id): ?TwoFaConnectorResponseInterface;
}