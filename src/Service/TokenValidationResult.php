<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\AccessToken;
use App\Entity\RefreshToken;

class TokenValidationResult
{
    public function __construct(
        private readonly AccessToken|RefreshToken $entity,
        private readonly array $decoded,
        private readonly TokenType $type,
    ) {}

    public function getEntity(): AccessToken|RefreshToken
    {
        return $this->entity;
    }

    public function getDecoded(): array
    {
        return $this->decoded;
    }

    public function getType(): TokenType
    {
        return $this->type;
    }
}