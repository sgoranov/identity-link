<?php
declare(strict_types=1);

namespace App\Security\Exception;

/**
 * The request does not come from the browser that started the authorization session.
 */
class BrowserMismatchAuthorizationSessionException extends AuthorizationSessionException
{

}
