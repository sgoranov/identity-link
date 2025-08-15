<?php
declare(strict_types=1);

namespace App\Security;

/**
 * Enum representing the steps in the login flow.
 *
 * The string value of each case corresponds to a Symfony route name.
 * These routes are used to redirect the user to the correct action
 * for handling that step of the authentication process.
 */
enum LoginStateEnum: string
{
    case PASSWORD = 'login_with_password';
    case TWO_FACTOR_INITIATE = 'login_2fa_initiate';
    case TWO_FACTOR_COMPLETE = 'login_2fa_complete';
    case COMPLETED = 'login_complete';

    public static function indexOf(self $state, array $flow): int|false
    {
        return array_search($state, $flow, true);
    }

    public static function fromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }

    public function routeName(): string
    {
        return $this->value;
    }
}
