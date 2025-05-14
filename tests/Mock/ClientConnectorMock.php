<?php
declare(strict_types=1);

namespace App\Tests\Mock;

use App\Api\Contract\ClientConnectorInterface;
use App\Api\IdentityLink\Response\GroupsResponse;
use App\DataFixtures\AppFixtures;
use App\LeagueOAuth2\Entity\ClientEntity;
use App\LeagueOAuth2\Entity\GrantTypeEntity;
use League\OAuth2\Server\Entities\ClientEntityInterface;

class ClientConnectorMock implements ClientConnectorInterface
{

    public function getClientByClientCredentials($clientIdentifier, $clientSecret, $grantType): ?\App\Api\Contract\ClientResponseInterface
    {
        if ($clientIdentifier === AppFixtures::PRIVATE_CLIENT_IDENTIFIER && $clientSecret === AppFixtures::PRIVATE_CLIENT_SECRET) {

            $client = new ClientEntity();

            $client->setPublic(false);
            $client->setIdentifier(AppFixtures::PRIVATE_CLIENT_IDENTIFIER);
            $client->setName(AppFixtures::PRIVATE_CLIENT_IDENTIFIER);
            $client->setRedirectUri(AppFixtures::PRIVATE_CLIENT_REDIRECT_URI);
            $client->setGrantTypes([
                GrantTypeEntity::CLIENT_CREDENTIALS,
                GrantTypeEntity::PASSWORD,
                GrantTypeEntity::AUTHORIZATION_CODE,
                GrantTypeEntity::REFRESH_TOKEN,
                GrantTypeEntity::IMPLICIT,
            ]);
            $client->setScopes([]);

            return $client;
        }

        return null;
    }

    public function getClientById(string $id): ?\App\Api\Contract\ClientResponseInterface
    {
        $client = new ClientEntity();

        if ($id === AppFixtures::PRIVATE_CLIENT_IDENTIFIER) {

            $client->setPublic(false);
            $client->setIdentifier(AppFixtures::PRIVATE_CLIENT_IDENTIFIER);
            $client->setName(AppFixtures::PRIVATE_CLIENT_IDENTIFIER);
            $client->setRedirectUri(AppFixtures::PRIVATE_CLIENT_REDIRECT_URI);
            $client->setGrantTypes([
                GrantTypeEntity::CLIENT_CREDENTIALS,
                GrantTypeEntity::PASSWORD,
                GrantTypeEntity::AUTHORIZATION_CODE,
                GrantTypeEntity::REFRESH_TOKEN,
                GrantTypeEntity::IMPLICIT,
            ]);
            $client->setScopes([]);

            return $client;

        } elseif ($id === AppFixtures::PUBLIC_CLIENT_IDENTIFIER) {

            $client->setPublic(true);
            $client->setIdentifier(AppFixtures::PUBLIC_CLIENT_IDENTIFIER);
            $client->setName(AppFixtures::PUBLIC_CLIENT_IDENTIFIER);
            $client->setRedirectUri(AppFixtures::PUBLIC_CLIENT_REDIRECT_URI);
            $client->setGrantTypes([
                GrantTypeEntity::CLIENT_CREDENTIALS,
            ]);
            $client->setScopes([]);

            return $client;
        }

        return null;
    }

    public function getGroups(string $uuid, int $limit): GroupsResponse
    {
        $response = new GroupsResponse();
        $response->setGroups([]);

        return $response;
    }
}