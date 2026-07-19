<?php
declare(strict_types=1);

namespace App\EventSubscriber;

use App\Api\Contract\ClientConnectorInterface;
use App\Security\Exception\AuthRequestException;
use App\Security\Exception\ConsumedAuthRequestException;
use App\Security\Exception\ExpiredAuthRequestException;
use App\Security\Exception\InvalidAuthRequestException;
use App\Security\Exception\InvalidLoginStateException;
use App\Security\Exception\LoginFlowException;
use App\Security\Exception\UserNotFoundException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class LoginFlowExceptionSubscriber implements EventSubscriberInterface
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
        if (!$exception instanceof LoginFlowException) {
            return;
        }

        $messageKey = match (true) {
            $exception instanceof UserNotFoundException => 'login_flow.user_not_found',
            $exception instanceof InvalidLoginStateException => 'login_flow.invalid_state',
        };

        $authRequest = $exception->authRequest;
        $client = $this->clientConnector->getClientById($authRequest->getClientId());

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