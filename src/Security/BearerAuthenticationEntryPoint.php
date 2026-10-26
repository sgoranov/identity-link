<?php
declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Answers a request to a protected endpoint that carries no bearer token with the challenge of RFC 6750 §3.
 *
 * Symfony's access token authenticator only handles requests that carry a token: it answers an invalid one with 401
 * and `WWW-Authenticate: Bearer error="invalid_token"`. A request without a token never reaches it, and without an
 * entry point the firewall throws an HttpException that becomes a 401 with no challenge. RFC 6750 §3.1 says that the
 * challenge for such a request should not include an error code, since the client may not know that the endpoint
 * needs authentication.
 */
final class BearerAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new Response(null, Response::HTTP_UNAUTHORIZED, ['WWW-Authenticate' => 'Bearer']);
    }
}
