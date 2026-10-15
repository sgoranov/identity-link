<?php
declare(strict_types=1);

namespace App\EventSubscriber;

use App\Api\Contract\ClientConnectorInterface;
use App\Security\Exception\AuthorizationSessionException;
use App\Security\Exception\BrowserMismatchAuthorizationSessionException;
use App\Security\Exception\ConsumedAuthorizationSessionException;
use App\Security\Exception\ExpiredAuthorizationSessionException;
use App\Security\Exception\InvalidAuthorizationSessionException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class AuthorizationSessionExceptionSubscriber implements EventSubscriberInterface
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
            KernelEvents::EXCEPTION => ['onException', 100],
        ];
    }

    public function onException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof AuthorizationSessionException) {
            return;
        }

        $messageKey = match (true) {
            $exception instanceof InvalidAuthorizationSessionException => 'authorization_session.invalid',
            $exception instanceof ExpiredAuthorizationSessionException => 'authorization_session.expired',
            $exception instanceof ConsumedAuthorizationSessionException => 'authorization_session.consumed',
            $exception instanceof BrowserMismatchAuthorizationSessionException => 'authorization_session.browser_mismatch',
        };

        $authorizationSession = $exception->authorizationSession;
        $client = $authorizationSession ? $this->clientConnector->getClientById($authorizationSession->getClientId()) : null;

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