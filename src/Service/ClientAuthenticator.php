<?php
declare(strict_types=1);

namespace App\Service;

use App\Api\Contract\ClientConnectorInterface;
use App\Api\Contract\ClientResponseInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ClientAuthenticator
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ClientConnectorInterface $clientConnector,
    ) {
    }

    /**
     * Extracts OAuth2 client credentials from the current request.
     *
     * - For confidential clients: expects HTTP Basic auth header in the form "Basic base64(clientId:clientSecret)".
     * - For public clients: expects "client_id" parameter in the request body (no secret required).
     *
     * @return array [clientId, clientSecret]
     */
    public function extractCredentials(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $authHeader = $request->headers->get('Authorization');

        $clientId = $clientSecret = null;

        if ($authHeader && str_starts_with($authHeader, 'Basic ')) {
            $decoded = base64_decode(substr($authHeader, 6), true);
            if ($decoded !== false && str_contains($decoded, ':')) {
                [$clientId, $clientSecret] = explode(':', $decoded, 2);
            }
        } else {
            // public client - client id must be passed in the request body
            $clientId = $request->request->get('client_id');
        }

        return [$clientId, $clientSecret];
    }

    public function authenticate(string $clientId, ?string $clientSecret = null): ?ClientResponseInterface
    {
        // public client
        if ($clientSecret === null) {
            $client = $this->clientConnector->getClientById($clientId);;

            return ($client !== null && $client->isPublic())
                ? $client
                : null;
        }

        return $this->clientConnector->getClientByClientCredentials(
            $clientId, $clientSecret, null
        );
    }
}