<?php
declare(strict_types=1);

namespace App\Security;

use App\Api\Contract\UserConnectorInterface;
use App\Entity\AuthRequest;

class LoginFlowFactory
{
    public function __construct(
        private readonly LoginFlowConfig $loginFlowConfig,
        private readonly UserConnectorInterface $userConnector,
    )
    {
    }

    public function create(AuthRequest $authRequest): LoginFlow
    {
        return new LoginFlow(
            $this->loginFlowConfig,
            $this->userConnector,
            $authRequest,
        );
    }
}