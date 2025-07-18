<?php
declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AccessToken;
use App\Entity\AuthCode;
use App\Entity\RefreshToken;
use App\LeagueOAuth2\Entity\ScopeEntity;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When(env: "test")]
#[When(env: "dev")]
class AppFixtures extends Fixture
{
    const PUBLIC_CLIENT_IDENTIFIER = 'e987c3ed-381a-4cec-8275-d338610202f7';
    const PUBLIC_CLIENT_REDIRECT_URI = 'http://localhost/public';

    const PRIVATE_CLIENT_IDENTIFIER = 'd72b9d7a-5bf3-441e-a649-14ef363afe22';
    const PRIVATE_CLIENT_SECRET = '2b740e1d-1655-4ad5-8f20-ee37e4e47f82';
    const PRIVATE_CLIENT_EXPIRED_SECRET = '2b740e1d-1655-4ad5-8f20-ee37e4e47f83';
    const PRIVATE_CLIENT_REDIRECT_URI = 'http://localhost';

    const AUTH_CODE_PRIVATE_CLIENT_IDENTIFIER = '000d19bd-4be7-4ce6-ba52f-ab7575ffd840';

    const AUTH_CODE_PUBLIC_CLIENT_IDENTIFIER = '000d19bd-4be7-4ce6-ba52-ab7575ffd841';

    const USER_IDENTIFIER = '7c1b7d1b-f624-4966-8f2a-e63ddfc34dba';
    const USER_PASSWORD = 'f1080c74-ace7-44e8-8512-d2917d6dcde6';

    const ACCESS_TOKEN_IDENTIFIER = '6fbc4538-e365-479b-84d9-c881f3259c3f';
    const EXPIRED_ACCESS_TOKEN_IDENTIFIER = '32385188-e1bc-46cc-85a3-29217c90086c';
    const CLIENT_CREDENTIALS_ACCESS_TOKEN_IDENTIFIER = 'd72e892c-1d07-4385-8455-3d65f94709f0';
    const REFRESH_TOKEN_IDENTIFIER = '0e57c42d-4537-4ab4-8b51-328ba75ab9c7';
    const ANOTHER_REFRESH_TOKEN_IDENTIFIER = '69750b5f-2651-4aa5-8ab6-df0727049cc0';

    public function load(ObjectManager $manager): void
    {
        $code = new AuthCode();
        $code->setClientIdentifier(self::PUBLIC_CLIENT_IDENTIFIER);
        $code->setIsRevoked(false);
        $code->setScopes(json_encode(['openid']));
        $code->setIdentifier(self::AUTH_CODE_PUBLIC_CLIENT_IDENTIFIER);
        $code->setUserIdentifier(self::USER_IDENTIFIER);
        $code->setExpiryDateTime((new \DateTimeImmutable())->modify('+1 day'));
        $code->setRedirectUri(self::PUBLIC_CLIENT_REDIRECT_URI);
        $manager->persist($code);

        // auth code
        $code = new AuthCode();
        $code->setClientIdentifier(self::PRIVATE_CLIENT_IDENTIFIER);
        $code->setIsRevoked(false);
        $code->setScopes(json_encode(['openid']));
        $code->setIdentifier(self::AUTH_CODE_PRIVATE_CLIENT_IDENTIFIER);
        $code->setUserIdentifier(self::USER_IDENTIFIER);
        $code->setExpiryDateTime((new \DateTimeImmutable())->modify('+1 day'));
        $code->setRedirectUri(self::PRIVATE_CLIENT_REDIRECT_URI);
        $manager->persist($code);

        // access token
        $accessToken = new AccessToken();
        $accessToken->setClientIdentifier(self::PRIVATE_CLIENT_IDENTIFIER);
        $accessToken->setIsRevoked(false);
        $accessToken->setScopes(json_encode(['openid']));
        $accessToken->setIdentifier(self::ACCESS_TOKEN_IDENTIFIER);
        $accessToken->setUserIdentifier(self::USER_IDENTIFIER);
        $accessToken->setExpiryDateTime((new \DateTimeImmutable())->modify('+1 day'));
        $manager->persist($accessToken);

        // expired access token
        $expiredAccessToken = new AccessToken();
        $expiredAccessToken->setClientIdentifier(self::PRIVATE_CLIENT_IDENTIFIER);
        $expiredAccessToken->setIsRevoked(false);
        $expiredAccessToken->setScopes(json_encode(['openid']));
        $expiredAccessToken->setIdentifier(self::EXPIRED_ACCESS_TOKEN_IDENTIFIER);
        $expiredAccessToken->setUserIdentifier(self::USER_IDENTIFIER);
        $expiredAccessToken->setExpiryDateTime((new \DateTimeImmutable())->modify('-1 day'));
        $manager->persist($expiredAccessToken);

        // client credentials access token
        $clientCredentialsAccessToken = new AccessToken();
        $clientCredentialsAccessToken->setClientIdentifier(self::PRIVATE_CLIENT_IDENTIFIER);
        $clientCredentialsAccessToken->setIsRevoked(false);
        $clientCredentialsAccessToken->setScopes(json_encode(['openid']));
        $clientCredentialsAccessToken->setIdentifier(self::CLIENT_CREDENTIALS_ACCESS_TOKEN_IDENTIFIER);
        $clientCredentialsAccessToken->setExpiryDateTime((new \DateTimeImmutable())->modify('+1 day'));
        $manager->persist($clientCredentialsAccessToken);

        // refresh token
        $refreshToken = new RefreshToken();
        $refreshToken->setAccessToken($accessToken);
        $refreshToken->setUserIdentifier($accessToken->getUserIdentifier());
        $refreshToken->setIsRevoked(false);
        $refreshToken->setExpiryDateTime((new \DateTimeImmutable())->modify('+1 day'));
        $refreshToken->setIdentifier(self::REFRESH_TOKEN_IDENTIFIER);
        $manager->persist($refreshToken);

        $refreshToken = new RefreshToken();
        $refreshToken->setAccessToken($expiredAccessToken);
        $refreshToken->setUserIdentifier($expiredAccessToken->getUserIdentifier());
        $refreshToken->setIsRevoked(false);
        $refreshToken->setExpiryDateTime((new \DateTimeImmutable())->modify('+1 day'));
        $refreshToken->setIdentifier(self::ANOTHER_REFRESH_TOKEN_IDENTIFIER);
        $manager->persist($refreshToken);

        $manager->flush();
    }
}
