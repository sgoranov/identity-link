<?php
declare(strict_types=1);

namespace App\Security\Exception;

use App\Entity\AuthRequest;

abstract class AuthRequestException extends \RuntimeException
{
    public function __construct(
        public readonly ?AuthRequest $authRequest = null
    )
    {
        parent::__construct();
    }
}