<?php
declare(strict_types=1);

namespace App\Security\Exception;

use App\Entity\AuthRequest;

final class ExpiredAuthRequestException extends AuthRequestException
{

}