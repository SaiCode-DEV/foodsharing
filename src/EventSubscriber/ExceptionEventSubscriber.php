<?php

declare(strict_types=1);

namespace Foodsharing\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Catches all exceptions that were thrown by Symfony. This class makes sure that exceptions from the Rest API are
 * returned to the client as JSON. When setResponse is called, the response is immediately sent to the client. All
 * other exceptions are left untouched, allowing Symfony to forward them to the custom error controller.
 */
class ExceptionEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $uri = $event->getRequest()->getRequestUri();
        $exception = $event->getThrowable();
        if (str_starts_with($uri, '/api')) {
            $statusCode = $exception instanceof HttpException ? $exception->getStatusCode() : 500;
            $event->setResponse(new JsonResponse([
                'message' => $exception->getMessage(),
                'code' => $statusCode,
            ], $statusCode));
        }
    }
}
