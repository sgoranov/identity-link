<?php
declare(strict_types=1);

namespace App\EventSubscriber;

use App\Api\Contract\ClientConnectorInterface;
use App\Security\Exception\AuthorizationSessionException;
use App\Security\Exception\ConsumedAuthorizationSessionException;
use App\Security\Exception\ExpiredAuthorizationSessionException;
use App\Security\Exception\InvalidAuthorizationSessionException;
use App\Security\Exception\InvalidLoginStateException;
use App\Security\Exception\AuthorizationFlowException;
use App\Security\Exception\UserNotFoundException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class AuthorizationFlowExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ClientConnectorInterface $clientConnector,
        private readonly Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onException', 101],
        ];
    }

    public function onException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof AuthorizationFlowException) {
            return;
        }

        $messageKey = match (true) {
            $exception instanceof UserNotFoundException => 'authorization_flow.user_not_found',
            $exception instanceof InvalidLoginStateException => 'authorization_flow.invalid_state',
        };

        $authorizationSession = $exception->authorizationSession;
        $client = $this->clientConnector->getClientById($authorizationSession->getClientId());

        $response = new Response(
            $this->twig->render('invalid_session.html.twig', [
                'message' => $this->translator->trans($messageKey),
                'client' => $client,
            ]),
            Response::HTTP_BAD_REQUEST
        );

        $event->setResponse($response);
    }
}