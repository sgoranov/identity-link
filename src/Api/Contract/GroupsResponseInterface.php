<?php
declare(strict_types=1);

namespace App\Api\Contract;

interface GroupsResponseInterface
{
    public function getGroups(): array;
}