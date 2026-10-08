<?php
declare(strict_types=1);

namespace App\Security\Exception;

use App\Entity\AuthorizationSession;

final class UserNotFoundException extends AuthorizationFlowException
{
    public function __construct(AuthorizationSession $authorizationSession, public readonly string $userIdentifier)
    {
        parent::__construct($authorizationSession);
    }
}