<?php
declare(strict_types=1);

namespace App\Security\Exception;

use App\Entity\AuthRequest;
use App\Security\LoginStateEnum;

class InvalidLoginStateException extends AuthorizationFlowException
{
    public function __construct(AuthRequest $authRequest, public readonly LoginStateEnum $state)
    {
        parent::__construct($authRequest);
    }
}