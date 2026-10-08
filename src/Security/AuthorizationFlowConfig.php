<?php
declare(strict_types=1);

namespace App\Security;

final class AuthorizationFlowConfig
{
    public function __construct(
        public readonly bool $twoFactorEnabled,
    )
    {
    }
}