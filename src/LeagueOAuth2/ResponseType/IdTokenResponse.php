<?php
declare(strict_types=1);

namespace App\LeagueOAuth2\ResponseType;

use App\Security\Jwt\JwtConfig;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
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
}
