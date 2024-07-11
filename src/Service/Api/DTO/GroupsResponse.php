<?php

namespace App\Service\Api\DTO;

use Symfony\Component\Serializer\Annotation\SerializedName;

class GroupsResponse
{
    #[SerializedName('result')]
    private array $groups;

    private bool $hasMore;

    public function getGroups(): array
    {
        return $this->groups;
    }

    public function setGroups(array $groups): void
    {
        $this->groups = $groups;
    }

    public function isHasMore(): bool
    {
        return $this->hasMore;
    }

    public function setHasMore(bool $hasMore): void
    {
        $this->hasMore = $hasMore;
    }
}