<?php
declare(strict_types=1);

namespace App\Api\IdentityLink\Response;

use App\Api\Contract\GroupsResponseInterface;
use Symfony\Component\Serializer\Annotation\SerializedName;

class GroupsResponse implements GroupsResponseInterface
{
    #[SerializedName('result')]
    private array $groups;

    public function getGroups(): array
    {
        return $this->groups;
    }

    public function setGroups(array $groups): void
    {
        $this->groups = $groups;
    }
}