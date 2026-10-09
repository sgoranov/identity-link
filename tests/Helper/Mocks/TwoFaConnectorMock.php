<?php
declare(strict_types=1);

namespace App\Tests\Helper\Mocks;

use App\Api\Contract\TwoFaConnectorInterface;
use App\Api\Contract\TwoFaConnectorResponseInterface;
use App\Api\IdentityLink\Response\AuthResponse;

class TwoFaConnectorMock implements TwoFaConnectorInterface
{
    /** @var array<string, string> 2FA request id => user identifier */
    private array $requests = [];

    /** @var array<string, true> ids of the requests the user has passed */
    private array $approved = [];

    /**
     * Simulates the user passing the second factor on the 2FA service.
     */
    public function approve(string $id): void
    {
        $this->approved[$id] = true;
    }

    public function initiateAuthenticationRequest(string $userIdentifier, string $redirectUri): ?string
    {
        $id = bin2hex(random_bytes(16));
        $this->requests[$id] = $userIdentifier;

        return $id;
    }

    public function validateAuthenticationRequest(string $id): ?TwoFaConnectorResponseInterface
    {
        // like the 2FA service, a request is only valid once the user has passed it
        if (!isset($this->requests[$id], $this->approved[$id])) {
            return null;
        }

        $response = new AuthResponse();
        $response->setId($id);
        $response->setUserId($this->requests[$id]);

        return $response;
    }
}
