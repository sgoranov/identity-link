<?php
declare(strict_types=1);

namespace App\Security\Exception;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

class RateLimitException extends AuthenticationException
{
    private array $params;

    public function __construct(int $minutes, int $seconds)
    {
        parent::__construct();

        $this->params = [
            '%minutes%' => $minutes,
            '%seconds%' => $seconds,
        ];
    }

    public function getMessageKey(): string
    {
        return 'login.too_many_attempts';
    }
    public function getMessageData(): array
    {
        return $this->params;
    }
}
