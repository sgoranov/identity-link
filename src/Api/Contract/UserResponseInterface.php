<?php
declare(strict_types=1);

namespace App\Api\Contract;

interface UserResponseInterface
{

    public function getId(): string;
    public function getClaims(): array;
}