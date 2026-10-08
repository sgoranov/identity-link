<?php
declare(strict_types=1);

namespace App\Security\Exception;

use App\Entity\AuthRequest;

final class UserNotFoundException extends AuthorizationFlowException
{
    public function __construct(AuthRequest $authRequest, public readonly string $userIdentifier)
    {
        parent::__construct($authRequest);
    }
}