<?php
declare(strict_types=1);

namespace App\Security;

final class LoginFlowConfig
{
    public function __construct(
        public readonly bool $twoFactorEnabled,
    )
    {
    }
}