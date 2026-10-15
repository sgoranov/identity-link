<?php
declare(strict_types=1);

namespace App\Security\Exception;

use App\Entity\AuthorizationSession;

abstract class AuthorizationFlowException extends \RuntimeException
{
    public function __construct(
        public readonly ?AuthorizationSession $authorizationSession = null
    )
    {
        parent::__construct();
    }
}