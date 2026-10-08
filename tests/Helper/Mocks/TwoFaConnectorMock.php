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

    public function initiateAuthenticationRequest(string $userIdentifier, string $redirectUri): ?string
    {
        $id = bin2hex(random_bytes(16));
        $this->requests[$id] = $userIdentifier;

        return $id;
    }

    public function validateAuthenticationRequest(string $id): ?TwoFaConnectorResponseInterface
    {
        if (!isset($this->requests[$id])) {
            return null;
        }

        $response = new AuthResponse();
        $response->setId($id);
        $response->setUserId($this->requests[$id]);

        return $response;
    }
}
