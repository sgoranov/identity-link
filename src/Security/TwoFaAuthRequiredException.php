<?php

namespace App\Security;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

class TwoFaAuthRequiredException extends AuthenticationException
{
    private string $authId;

    public function __construct(string $authId, $message = '')
    {
        parent::__construct($message);
        $this->authId = $authId;
    }

    public function getAuthId(): string
    {
        return $this->authId;
    }
}