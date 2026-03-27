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
            $message = $exception->getMessage();
            $response = new JsonResponse([
                'message' => $message,
                'code' => $statusCode,
            ], $statusCode);
            $response->setEncodingOptions(
                $response->getEncodingOptions()
                | JSON_UNESCAPED_UNICODE
                | JSON_PARTIAL_OUTPUT_ON_ERROR
                | JSON_INVALID_UTF8_SUBSTITUTE
            );
            $event->setResponse($response);
        }
    }
}
