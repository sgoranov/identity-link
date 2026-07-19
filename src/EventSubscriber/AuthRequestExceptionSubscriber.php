<?php
declare(strict_types=1);

namespace App\EventSubscriber;

use App\Api\Contract\ClientConnectorInterface;
use App\Security\Exception\AuthRequestException;
use App\Security\Exception\ConsumedAuthRequestException;
use App\Security\Exception\ExpiredAuthRequestException;
use App\Security\Exception\InvalidAuthRequestException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class AuthRequestExceptionSubscriber implements EventSubscriberInterface
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
        if (!$exception instanceof AuthRequestException) {
            return;
        }

        $messageKey = match (true) {
            $exception instanceof InvalidAuthRequestException => 'auth_request.invalid',
            $exception instanceof ExpiredAuthRequestException => 'auth_request.expired',
            $exception instanceof ConsumedAuthRequestException => 'auth_request.consumed',
        };

        $authRequest = $exception->authRequest;
        $client = $authRequest ? $this->clientConnector->getClientById($authRequest->getClientId()) : null;

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