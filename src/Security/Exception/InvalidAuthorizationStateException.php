<?php
declare(strict_types=1);

namespace App\Security\Exception;

use App\Entity\AuthorizationSession;
use App\Security\AuthorizationStateEnum;

class InvalidAuthorizationStateException extends AuthorizationFlowException
{
    public function __construct(AuthorizationSession $authorizationSession, public readonly AuthorizationStateEnum $state)
    {
        parent::__construct($authorizationSession);
    }
}