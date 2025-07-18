<?php
declare(strict_types=1);

namespace App\LeagueOAuth2\Entity;

use App\LeagueOAuth2\Entity\Traits\SerializableTrait;

class GrantTypeEntity
{
    use SerializableTrait;

    const CLIENT_CREDENTIALS = 'client_credentials';
    const PASSWORD = 'password';
    const AUTHORIZATION_CODE = 'authorization_code';
    const REFRESH_TOKEN = 'refresh_token';
    const IMPLICIT = 'implicit';
}