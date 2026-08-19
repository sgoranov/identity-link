<?php
declare(strict_types=1);

namespace App\LeagueOAuth2\ResponseType;

use App\Security\Jwt\JwtConfig;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use Lcobucci\JWT\Token\Builder;
use OpenIDConnectServer\ClaimExtractor;
use OpenIDConnectServer\Repositories\IdentityProviderInterface;

final class IdTokenResponse extends \OpenIDConnectServer\IdTokenResponse
{
    public function __construct(
        IdentityProviderInterface $identityProvider,
        ClaimExtractor $claimExtractor,
        private readonly JwtConfig $jwtConfig,
    ) {
        parent::__construct($identityProvider, $claimExtractor, $jwtConfig->getKid());
    }

    protected function getBuilder(
        AccessTokenEntityInterface $accessToken,
        UserEntityInterface $userEntity,
    ) {
        $builder = new Builder(new JoseEncoder(), ChainedFormatter::withUnixTimestampDates());
        $expiresAt = $accessToken->getExpiryDateTime();

        if ($expiresAt instanceof \DateTime) {
            $expiresAt = \DateTimeImmutable::createFromMutable($expiresAt);
        }

        return $builder
            ->permittedFor($accessToken->getClient()->getIdentifier())
            ->issuedBy($this->jwtConfig->getIssuer())
            ->issuedAt(new \DateTimeImmutable())
            ->expiresAt($expiresAt)
            ->relatedTo($userEntity->getIdentifier());
    }

    /**
     * Always expose the actual granted scopes in the token response.
     *
     * RFC 6749 requires `scope` when the granted scopes differ from those requested. Returning it consistently also
     * covers default or omitted scopes and supports OAuth clients that expect this standard response parameter,
     * without requiring them to decode the access token.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc6749#section-3.3
     * @see https://datatracker.ietf.org/doc/html/rfc6749#section-5.1
     */
    protected function getExtraParams(AccessTokenEntityInterface $accessToken): array
    {
        $params = parent::getExtraParams($accessToken);

        $params['scope'] = implode(' ', array_map(
            static fn (ScopeEntityInterface $scope): string => $scope->getIdentifier(),
            $accessToken->getScopes(),
        ));

        return $params;
    }
}
