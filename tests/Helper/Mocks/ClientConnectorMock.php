<?php
declare(strict_types=1);

namespace App\Tests\Helper\Mocks;

use App\Api\Contract\ClientConnectorInterface;
use App\Api\Contract\ClientResponseInterface;
use App\Api\IdentityLink\Response\DbClientResponse;
use App\Api\IdentityLink\Response\GroupsResponse;
use App\DataFixtures\AppFixtures;
use App\LeagueOAuth2\Entity\GrantTypeEntity;

class ClientConnectorMock implements ClientConnectorInterface
{

    public function getClientByClientCredentials($clientIdentifier, $clientSecret, $grantType): ?ClientResponseInterface
    {
        if ($clientIdentifier === AppFixtures::PRIVATE_CLIENT_IDENTIFIER && $clientSecret === AppFixtures::PRIVATE_CLIENT_SECRET) {

            $response = new DbClientResponse();
            $response->setPublic(false);
            $response->setConsentRequired(true);
            $response->setId(AppFixtures::PRIVATE_CLIENT_IDENTIFIER);
            $response->setName(AppFixtures::PRIVATE_CLIENT_IDENTIFIER);
            $response->setRedirectUri(AppFixtures::PRIVATE_CLIENT_REDIRECT_URI);
            $response->setGrantTypes([
                GrantTypeEntity::CLIENT_CREDENTIALS,
                GrantTypeEntity::PASSWORD,
                GrantTypeEntity::AUTHORIZATION_CODE,
                GrantTypeEntity::REFRESH_TOKEN,
                GrantTypeEntity::IMPLICIT,
            ]);
            $response->setAudience('https://example.com/identity-link');
            $response->setScopes([]);

            return $response;
        }

        if ($clientIdentifier === AppFixtures::MULTI_REDIRECT_CLIENT_IDENTIFIER && $clientSecret === AppFixtures::MULTI_REDIRECT_CLIENT_SECRET) {

            $response = new DbClientResponse();
            $response->setPublic(false);
            $response->setConsentRequired(true);
            $response->setId(AppFixtures::MULTI_REDIRECT_CLIENT_IDENTIFIER);
            $response->setName(AppFixtures::MULTI_REDIRECT_CLIENT_IDENTIFIER);
            $response->setRedirectUri(AppFixtures::MULTI_REDIRECT_CLIENT_REDIRECT_URIS);
            $response->setGrantTypes([
                GrantTypeEntity::AUTHORIZATION_CODE,
            ]);
            $response->setAudience('https://example.com/identity-link');
            $response->setScopes([]);

            return $response;
        }

        return null;
    }

    public function getClientById(string $id): ?ClientResponseInterface
    {
        $response = new DbClientResponse();

        if ($id === AppFixtures::PRIVATE_CLIENT_IDENTIFIER) {

            $response->setPublic(false);
            $response->setConsentRequired(true);
            $response->setId(AppFixtures::PRIVATE_CLIENT_IDENTIFIER);
            $response->setName(AppFixtures::PRIVATE_CLIENT_IDENTIFIER);
            $response->setRedirectUri(AppFixtures::PRIVATE_CLIENT_REDIRECT_URI);
            $response->setGrantTypes([
                GrantTypeEntity::CLIENT_CREDENTIALS,
                GrantTypeEntity::PASSWORD,
                GrantTypeEntity::AUTHORIZATION_CODE,
                GrantTypeEntity::REFRESH_TOKEN,
                GrantTypeEntity::IMPLICIT,
            ]);
            $response->setAudience('https://example.com/identity-link');
            $response->setScopes([]);

            return $response;

        } elseif ($id === AppFixtures::MULTI_REDIRECT_CLIENT_IDENTIFIER) {

            $response->setPublic(false);
            $response->setConsentRequired(true);
            $response->setId(AppFixtures::MULTI_REDIRECT_CLIENT_IDENTIFIER);
            $response->setName(AppFixtures::MULTI_REDIRECT_CLIENT_IDENTIFIER);
            $response->setRedirectUri(AppFixtures::MULTI_REDIRECT_CLIENT_REDIRECT_URIS);
            $response->setGrantTypes([
                GrantTypeEntity::AUTHORIZATION_CODE,
            ]);
            $response->setAudience('https://example.com/identity-link');
            $response->setScopes([]);

            return $response;

        } elseif ($id === AppFixtures::PUBLIC_CLIENT_IDENTIFIER) {

            $response->setPublic(true);
            $response->setConsentRequired(true);
            $response->setId(AppFixtures::PUBLIC_CLIENT_IDENTIFIER);
            $response->setName(AppFixtures::PUBLIC_CLIENT_IDENTIFIER);
            $response->setRedirectUri(AppFixtures::PUBLIC_CLIENT_REDIRECT_URI);
            $response->setGrantTypes([
                GrantTypeEntity::CLIENT_CREDENTIALS,
            ]);
            $response->setAudience('https://example.com/identity-link');
            $response->setScopes([]);

            return $response;
        }

        return null;
    }

    public function getGroups(string $uuid, int $limit): GroupsResponse
    {
        $response = new GroupsResponse();
        $response->setGroups([]);

        return $response;
    }

    public function getScopes(string $id, string $audience): array
    {
        return ['openid'];
    }
}
