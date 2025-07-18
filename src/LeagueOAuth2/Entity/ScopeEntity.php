<?php
declare(strict_types=1);

namespace App\LeagueOAuth2\Entity;

use App\LeagueOAuth2\Entity\Traits\SerializableTrait;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

class ScopeEntity implements ScopeEntityInterface
{
    use SerializableTrait;
}