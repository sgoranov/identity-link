<?php
declare(strict_types=1);

namespace App\Api\Contract;

interface ClientConnectorInterface
{
    public function getClientByClientCredentials($clientIdentifier, $clientSecret, $grantType): ?ClientResponseInterface;

    public function getClientById(string $id): ?ClientResponseInterface;

    public function getGroups(string $uuid, int $limit): GroupsResponseInterface;
}