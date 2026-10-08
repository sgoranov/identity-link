<?php
declare(strict_types=1);

namespace App\Security\Exception;

use App\Entity\AuthorizationSession;

final class ExpiredAuthorizationSessionException extends AuthorizationSessionException
{

}