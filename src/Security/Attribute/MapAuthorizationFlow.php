<?php
declare(strict_types=1);

namespace App\Security\Attribute;

use App\Security\AuthorizationFlowValueResolver;
use App\Security\AuthorizationStateEnum;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;

/**
 * Maps the {id} route parameter to the AuthorizationFlow of that authorization session,
 * after checking that the session is valid and that $state is its next step.
 *
 * An action can't receive the flow without passing these checks, so its signature states
 * the step it handles.
 *
 * @see AuthorizationFlowValueResolver
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class MapAuthorizationFlow extends ValueResolver
{
    public function __construct(
        public readonly AuthorizationStateEnum $state,
    ) {
        parent::__construct(AuthorizationFlowValueResolver::class);
    }
}
