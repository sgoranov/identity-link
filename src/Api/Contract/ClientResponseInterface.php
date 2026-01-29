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
}
