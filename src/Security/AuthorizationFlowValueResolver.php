<?php
declare(strict_types=1);

namespace App\Security;

use App\Security\Attribute\MapAuthorizationFlow;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsTargetedValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * Resolves arguments marked with #[MapAuthorizationFlow].
 *
 * The session must exist, not be expired and not be consumed (AuthorizationSessionResolver),
 * and the step of the action must be the next step of its flow (AuthorizationFlow::assertStateAllowed()).
 * Failures are thrown as exceptions and handled by the exception subscribers.
 */
#[AsTargetedValueResolver]
final class AuthorizationFlowValueResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly AuthorizationSessionResolver $authorizationSessionResolver,
        private readonly AuthorizationFlowFactory $authorizationFlowFactory,
    ) {
    }

    /**
     * @return iterable<AuthorizationFlow>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $attributes = $argument->getAttributesOfType(MapAuthorizationFlow::class, ArgumentMetadata::IS_INSTANCEOF);
        if ($attributes === []) {
            return [];
        }

        if ($argument->getType() !== AuthorizationFlow::class) {
            throw new \LogicException(sprintf('#[MapAuthorizationFlow] can only be used on an argument of type "%s", "$%s" is "%s".',
                AuthorizationFlow::class, $argument->getName(), $argument->getType()));
        }

        $id = $request->attributes->get('id');
        if (!is_string($id)) {
            throw new \LogicException(sprintf('#[MapAuthorizationFlow] requires an {id} route parameter, "$%s" has none.', $argument->getName()));
        }

        $authorizationSession = $this->authorizationSessionResolver->resolve($id);
        $authorizationFlow = $this->authorizationFlowFactory->create($authorizationSession);
        $authorizationFlow->assertStateAllowed($attributes[0]->state);

        return [$authorizationFlow];
    }
}
