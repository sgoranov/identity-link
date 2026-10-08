<?php
declare(strict_types=1);

namespace App\Security\Exception;

use App\Entity\AuthorizationSession;
use App\Security\LoginStateEnum;

class InvalidLoginStateException extends AuthorizationFlowException
{
    public function __construct(AuthorizationSession $authorizationSession, public readonly LoginStateEnum $state)
    {
        parent::__construct($authorizationSession);
    }
}