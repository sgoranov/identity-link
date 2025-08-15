<?php
declare(strict_types=1);

namespace App\Api\Contract;

interface TwoFaConnectorResponseInterface
{
    public function getUserId(): string;
}