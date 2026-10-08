<?php
declare(strict_types=1);

namespace App\Security;

use App\Api\Contract\ClientConnectorInterface;
use App\Api\Contract\UserConnectorInterface;
use App\Entity\AuthorizationSession;

class AuthorizationFlowFactory
{
    public function __construct(
        private readonly AuthorizationFlowConfig $authorizationFlowConfig,
        private readonly UserConnectorInterface $userConnector,
        private readonly ClientConnectorInterface $clientConnector,
    )
    {
    }

    public function create(AuthorizationSession $authorizationSession): AuthorizationFlow
    {
        return new AuthorizationFlow(
            $this->authorizationFlowConfig,
            $this->userConnector,
            $this->clientConnector,
            $authorizationSession,
        );
    }
}