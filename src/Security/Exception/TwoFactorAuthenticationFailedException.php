<?php
declare(strict_types=1);

namespace App\Security\Exception;

use App\Entity\AuthorizationSession;

/**
 * The 2FA request initiated for the authorization session was not passed, or not by its user.
 */
final class TwoFactorAuthenticationFailedException extends AuthorizationFlowException
{
    public function __construct(AuthorizationSession $authorizationSession)
    {
        parent::__construct($authorizationSession);
    }
}
