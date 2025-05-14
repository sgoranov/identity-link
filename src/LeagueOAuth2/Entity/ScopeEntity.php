<?php
declare(strict_types=1);

namespace App\LeagueOAuth2\Entity;

use App\LeagueOAuth2\Entity\Traits\SerializableTrait;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

class ScopeEntity implements ScopeEntityInterface
{
    use SerializableTrait;

    const OPENID = 'openid';
    const PROFILE = 'profile';
    const GROUPS = 'groups';
    const OFFLINE_ACCESS = 'offline_access';

    public static function getSupported(): array
    {
        return [
            self::OPENID,
            self::PROFILE,
            self::GROUPS,
            self::OFFLINE_ACCESS,
        ];
    }

    public static function convertFromString(array $scopes): array
    {
        $result = [];
        foreach ($scopes as $scope) {
            $result[] = new self($scope);
        }

        return $result;
    }
}