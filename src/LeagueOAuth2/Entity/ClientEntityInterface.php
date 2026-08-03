<?php
declare(strict_types=1);

namespace App\LeagueOAuth2\Entity;

use League\OAuth2\Server\Entities\ClientEntityInterface as LeagueClientEntityInterface;

/**
 * Application-specific OAuth client data in addition to League's core client
 * contract.
 */
interface ClientEntityInterface extends LeagueClientEntityInterface
{
    public function getAudience(): string;

    /** @return list<string> */
    public function getScopes(): array;

    /** @return list<string> */
    public function getGrantTypes(): array;

    public function isPublic(): bool;
}
