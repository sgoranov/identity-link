<?php
declare(strict_types=1);

namespace App\Tests\Mock;

use App\DataFixtures\AppFixtures;
use App\Model\OAuth2\ClientModel;
use App\Model\OAuth2\GrantTypeModel;
use App\Service\Api\ClientConnectorInterface;
use App\Service\Api\DTO\GroupsResponse;
use League\OAuth2\Server\Entities\ClientEntityInterface;

class ClientConnectorMock implements ClientConnectorInterface
{

    public function getClientEntityByClientCredentials($clientIdentifier, $clientSecret, $grantType): ?ClientEntityInterface
    {
        if ($clientIdentifier === AppFixtures::PRIVATE_CLIENT_IDENTIFIER && $clientSecret === AppFixtures::PRIVATE_CLIENT_SECRET) {

            $client = new ClientModel();

            $client->setPublic(false);
            $client->setIdentifier(AppFixtures::PRIVATE_CLIENT_IDENTIFIER);
            $client->setName(AppFixtures::PRIVATE_CLIENT_IDENTIFIER);
            $client->setRedirectUri(AppFixtures::PRIVATE_CLIENT_REDIRECT_URI);
            $client->setGrantTypes([
                GrantTypeModel::CLIENT_CREDENTIALS,
                GrantTypeModel::PASSWORD,
                GrantTypeModel::AUTHORIZATION_CODE,
                GrantTypeModel::REFRESH_TOKEN,
                GrantTypeModel::IMPLICIT,
            ]);
            $client->setScopes([]);

            return $client;
        }

        return null;
    }

    public function getClientEntityById(string $id): ?ClientEntityInterface
    {
        $client = new ClientModel();

        if ($id === AppFixtures::PRIVATE_CLIENT_IDENTIFIER) {

            $client->setPublic(false);
            $client->setIdentifier(AppFixtures::PRIVATE_CLIENT_IDENTIFIER);
            $client->setName(AppFixtures::PRIVATE_CLIENT_IDENTIFIER);
            $client->setRedirectUri(AppFixtures::PRIVATE_CLIENT_REDIRECT_URI);
            $client->setGrantTypes([
                GrantTypeModel::CLIENT_CREDENTIALS,
                GrantTypeModel::PASSWORD,
                GrantTypeModel::AUTHORIZATION_CODE,
                GrantTypeModel::REFRESH_TOKEN,
                GrantTypeModel::IMPLICIT,
            ]);
            $client->setScopes([]);

            return $client;

        } elseif ($id === AppFixtures::PUBLIC_CLIENT_IDENTIFIER) {

            $client->setPublic(true);
            $client->setIdentifier(AppFixtures::PUBLIC_CLIENT_IDENTIFIER);
            $client->setName(AppFixtures::PUBLIC_CLIENT_IDENTIFIER);
            $client->setRedirectUri(AppFixtures::PUBLIC_CLIENT_REDIRECT_URI);
            $client->setGrantTypes([
                GrantTypeModel::CLIENT_CREDENTIALS,
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
        $response->setHasMore(false);

        return $response;
    }
}