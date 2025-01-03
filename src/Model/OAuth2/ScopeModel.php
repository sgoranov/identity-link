<?php
declare(strict_types=1);

namespace App\Model\OAuth2;

use App\Model\OAuth2\Traits\SerializableTrait;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

class ScopeModel implements ScopeEntityInterface
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