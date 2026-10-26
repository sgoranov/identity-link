<?php
declare(strict_types=1);

namespace App\Security\Authorization;

use RuntimeException;

final class UnknownAudienceException extends RuntimeException
{
}
