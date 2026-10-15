<?php
declare(strict_types=1);

namespace App\Security;

use App\Entity\AuthorizationSession;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds an authorization session to the browser that started it.
 *
 * The session id travels in the URL, so on its own anyone with the link could continue the
 * flow, e.g. an attacker who sends the victim a login link of a session they started and,
 * after the victim logs in, receives the code in their own browser. The browser that starts
 * the session gets a random secret in a cookie and only its hash is stored, so every step
 * also requires that cookie.
 *
 * The cookie is named per session, so parallel flows (e.g. in two tabs) don't overwrite it.
 */
class AuthorizationSessionBinding
{
    private const COOKIE_PREFIX = 'authorization_session_';

    /**
     * Requires the id of the session, which is assigned on persist.
     */
    public function bind(AuthorizationSession $authorizationSession, Response $response): void
    {
        $secret = bin2hex(random_bytes(32));
        $authorizationSession->setBrowserBindingHash($this->hash($secret));

        // SameSite=Lax, as the 2FA service sends the user back with a cross-site redirect.
        // Secure is left to Symfony, which sets it for HTTPS requests.
        $response->headers->setCookie(Cookie::create(
            $this->getCookieName($authorizationSession),
            $secret,
            $authorizationSession->getExpiresAt(),
            '/',
            null,
            null,
            true,
            false,
            Cookie::SAMESITE_LAX,
        ));
    }

    public function isBound(AuthorizationSession $authorizationSession, Request $request): bool
    {
        $secret = $request->cookies->get($this->getCookieName($authorizationSession));
        if (!is_string($secret)) {
            return false;
        }

        return hash_equals($authorizationSession->getBrowserBindingHash(), $this->hash($secret));
    }

    /**
     * Removes the cookie once the session is consumed.
     */
    public function unbind(AuthorizationSession $authorizationSession, Response $response): void
    {
        $response->headers->clearCookie($this->getCookieName($authorizationSession), '/', null, false, true, Cookie::SAMESITE_LAX);
    }

    private function getCookieName(AuthorizationSession $authorizationSession): string
    {
        $id = $authorizationSession->getId();
        if ($id === null) {
            throw new \LogicException('The authorization session must be persisted before it is bound to the browser.');
        }

        return self::COOKIE_PREFIX . $id;
    }

    private function hash(string $secret): string
    {
        return hash('sha256', $secret);
    }
}
