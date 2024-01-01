<?php

namespace Foodsharing\EventListener;

use Doctrine\Common\Annotations\Reader;
use Foodsharing\Annotation\DisableCsrfProtection;
use Foodsharing\Lib\Session;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

class CsrfListener
{
    public function __construct(
        private readonly Reader $reader,
        private readonly Session $session
    ) {
    }

    public function onKernelController(ControllerEvent $event)
    {
        if (!is_array($controllers = $event->getController())) {
            return;
        }

        $httpMethod = $event->getRequest()->getMethod();
        if (in_array($httpMethod, ['GET', 'OPTIONS', 'HEAD'])) {
            // since these methods should not cause any changes, we can savely execute cross site requests
            return;
        }

        list($controller, $methodName) = $controllers;
        $reflectionObject = new \ReflectionObject($controller);
        $namespaceName = $reflectionObject->getNamespaceName();
        if (!str_starts_with($namespaceName, 'Foodsharing\\RestApi')) {
            // This mechanism was only ever meant for the REST API,
            // which used to be the only Symfony controllers.
            // Since we started using Symfony for everything now,
            // this ignores all requests not handled by the REST controllers.
            return;
        }

        $reflectionMethod = $reflectionObject->getMethod($methodName);
        $methodAnnotation = $this->reader
            ->getMethodAnnotation($reflectionMethod, DisableCsrfProtection::class);

        if ($methodAnnotation) {
            // CSRF Protection is disabled for this method
            return;
        }

        if (!$this->session->isValidCsrfHeader()) {
            throw new SuspiciousOperationException('CSRF Failed: CSRF token missing or incorrect.');
        }
    }
}
