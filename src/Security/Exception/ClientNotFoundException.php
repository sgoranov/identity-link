<?php
declare(strict_types=1);

namespace App\Security\Exception;

use App\Entity\AuthorizationSession;

final class ClientNotFoundException extends AuthorizationFlowException
{
    public function __construct(AuthorizationSession $authorizationSession, public readonly string $clientIdentifier)
    {
        parent::__construct($authorizationSession);
    }
}
