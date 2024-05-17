<?php
declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AccessToken;
use App\Entity\AuthCode;
use App\Entity\Client;
use App\Entity\ClientSecret;
use App\Entity\RefreshToken;
use App\Model\OAuth2\GrantTypeModel;
use App\Model\OAuth2\ScopeModel;
use App\Service\PasswordHashGenerator;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When(env: "test")]
#[When(env: "dev")]
class AppFixtures extends Fixture
{
    const PUBLIC_CLIENT_IDENTIFIER = 'e48a3bce-773c-40b0-b50c-7ba11e41e062';
    const PUBLIC_CLIENT_REDIRECT_URI = 'http://localhost/public';

    const PRIVATE_CLIENT_IDENTIFIER = '9d080b69-fe45-49ab-95fe-4a1c9b860ca3';
    const PRIVATE_CLIENT_SECRET = '2b740e1d-1655-4ad5-8f20-ee37e4e47f82';
    const PRIVATE_CLIENT_EXPIRED_SECRET = '2b740e1d-1655-4ad5-8f20-ee37e4e47f83';
    const PRIVATE_CLIENT_NAME = 'fb85b097-d07a-42fd-b0e5-f701a81082a3';
    const PRIVATE_CLIENT_REDIRECT_URI = 'http://localhost';

    const AUTH_CODE_PRIVATE_CLIENT_IDENTIFIER = '000d19bd-4be7-4ce6-ba52f-ab7575ffd840';

    const AUTH_CODE_PUBLIC_CLIENT_IDENTIFIER = '000d19bd-4be7-4ce6-ba52-ab7575ffd841';

    const USER_IDENTIFIER = '7c1b7d1b-f624-4966-8f2a-e63ddfc34dba';
    const USER_PASSWORD = 'f1080c74-ace7-44e8-8512-d2917d6dcde6';

    const ACCESS_TOKEN_IDENTIFIER = '6fbc4538-e365-479b-84d9-c881f3259c3f';
    const REFRESH_TOKEN_IDENTIFIER = '0e57c42d-4537-4ab4-8b51-328ba75ab9c7';

    public function load(ObjectManager $manager): void
    {
        // public client
        $client = new Client();
        $client->setName('client_public');
        $client->setIdentifier(self::PUBLIC_CLIENT_IDENTIFIER);
        $client->setRedirectUri(self::PUBLIC_CLIENT_REDIRECT_URI);
        $client->setIsConfidential(false);
        $client->setGrantTypes(json_encode([GrantTypeModel::CLIENT_CREDENTIALS]));
        $client->setScopes(json_encode([]));
        $manager->persist($client);

        $code = new AuthCode();
        $code->setClientIdentifier($client->getIdentifier());
        $code->setIsRevoked(false);
        $code->setScopes(json_encode([ScopeModel::OPENID]));
        $code->setIdentifier(self::AUTH_CODE_PUBLIC_CLIENT_IDENTIFIER);
        $code->setUserIdentifier(self::USER_IDENTIFIER);
        $code->setExpiryDateTime((new \DateTimeImmutable())->modify('+1 day'));
        $code->setRedirectUri(self::PUBLIC_CLIENT_REDIRECT_URI);
        $manager->persist($code);

        // private client
        $client = new Client();
        $client->setName(self::PRIVATE_CLIENT_NAME);
        $client->setIdentifier(self::PRIVATE_CLIENT_IDENTIFIER);
        $client->setRedirectUri(self::PRIVATE_CLIENT_REDIRECT_URI);
        $client->setIsConfidential(true);
        $client->setGrantTypes(json_encode([
            GrantTypeModel::CLIENT_CREDENTIALS,
            GrantTypeModel::PASSWORD,
            GrantTypeModel::AUTHORIZATION_CODE,
            GrantTypeModel::REFRESH_TOKEN,
            GrantTypeModel::IMPLICIT,
        ]));
        $client->setScopes(json_encode([]));
        $manager->persist($client);

        $secret = new ClientSecret();
        $secret->setClient($client);
        $secret->setSecret(PasswordHashGenerator::create(self::PRIVATE_CLIENT_SECRET));
        $secret->setExpiryDateTime((new \DateTimeImmutable())->modify('+1 day'));
        $manager->persist($secret);

        $secret = new ClientSecret();
        $secret->setClient($client);
        $secret->setSecret(PasswordHashGenerator::create(self::PRIVATE_CLIENT_EXPIRED_SECRET));
        $secret->setExpiryDateTime((new \DateTimeImmutable())->modify('-1 day'));
        $manager->persist($secret);

        // auth code
        $code = new AuthCode();
        $code->setClientIdentifier($client->getIdentifier());
        $code->setIsRevoked(false);
        $code->setScopes(json_encode([ScopeModel::OPENID]));
        $code->setIdentifier(self::AUTH_CODE_PRIVATE_CLIENT_IDENTIFIER);
        $code->setUserIdentifier(self::USER_IDENTIFIER);
        $code->setExpiryDateTime((new \DateTimeImmutable())->modify('+1 day'));
        $code->setRedirectUri(self::PRIVATE_CLIENT_REDIRECT_URI);
        $manager->persist($code);

        // access token
        $accessToken = new AccessToken();
        $accessToken->setClientIdentifier($client->getIdentifier());
        $accessToken->setIsRevoked(false);
        $accessToken->setScopes(json_encode([ScopeModel::OPENID]));
        $accessToken->setIdentifier(self::ACCESS_TOKEN_IDENTIFIER);
        $accessToken->setUserIdentifier(self::USER_IDENTIFIER);
        $accessToken->setExpiryDateTime((new \DateTimeImmutable())->modify('+1 day'));
        $manager->persist($accessToken);

        // refresh token
        $refreshToken = new RefreshToken();
        $refreshToken->setAccessToken($accessToken);
        $refreshToken->setIsRevoked(false);
        $refreshToken->setExpiryDateTime((new \DateTimeImmutable())->modify('+1 day'));
        $refreshToken->setIdentifier(self::REFRESH_TOKEN_IDENTIFIER);
        $manager->persist($refreshToken);

        $manager->flush();
    }
}
