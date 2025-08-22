<?php
declare(strict_types=1);

namespace App\Service;

enum TokenType: string
{
    case ACCESS = 'access_token';
    case REFRESH = 'refresh_token';
}
