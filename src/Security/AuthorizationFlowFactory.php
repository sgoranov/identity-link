<?php
declare(strict_types=1);

namespace App\Security;

use App\Api\Contract\UserConnectorInterface;
use App\Entity\AuthorizationSession;

class AuthorizationFlowFactory
{
    public function __construct(
        private readonly AuthorizationFlowConfig $authorizationFlowConfig,
        private readonly UserConnectorInterface $userConnector,
    )
    {
    }

    public function create(AuthorizationSession $authorizationSession): AuthorizationFlow
    {
        return new AuthorizationFlow(
            $this->authorizationFlowConfig,
            $this->userConnector,
            $authorizationSession,
        );
    }
}