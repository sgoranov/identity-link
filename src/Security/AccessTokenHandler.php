<?php
declare(strict_types=1);

namespace App\Security;

use App\Repository\AccessTokenRepository;
use sgoranov\IdentityLinkShared\Security\AccessTokenHandler as SharedAccessTokenHandler;
use sgoranov\IdentityLinkShared\Security\User;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Authenticates bearer tokens on this server's protected endpoints, rejecting revoked access tokens.
 *
 * The shared handler only checks the JWT itself (signature, issuer, audience, expiry), so that the other services can
 * use it without access to this server's database. Revocation, by the revocation endpoint or by single sign-off, is
 * recorded only in the access_token table. Without this class a revoked access token keeps working here until it
 * expires.
 *
 * Every access token issued by the authorization server is stored under its jti. Tokens without a jti are the
 * internal tokens that JwtTokenGenerator creates for calls to the other services; they are not stored and cannot be
 * revoked, so they are accepted as before.
 */
final class AccessTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private readonly SharedAccessTokenHandler $sharedAccessTokenHandler,
        private readonly AccessTokenRepository $accessTokenRepository,
    )
    {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $userBadge = $this->sharedAccessTokenHandler->getUserBadgeFrom($accessToken);

        /** @var User $user */
        $user = $userBadge->getUser();
        $jti = $user->getAccessToken()->jti ?? null;
        if (null === $jti) {
            return $userBadge;
        }

        $storedToken = is_string($jti) ? $this->accessTokenRepository->getByIdentifier($jti) : null;
        if (null === $storedToken || $storedToken->isRevoked()) {
            throw new BadCredentialsException('The access token is revoked or unknown.');
        }

        return $userBadge;
    }
}
