<?php

namespace Foodsharing\RestApi;

use Carbon\Carbon;
use DateTime;
use Foodsharing\Lib\Session;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * General class that contains common functions for all REST controllers.
 */
abstract class AbstractFoodsharingRestController extends AbstractFOSRestController
{
    public function __construct(
        protected Session $session,
    ) {
    }

    /**
     * Checks if the request violates the specified rate limiting and throws an exception if it does. If
     * the rate limiting is not violated, the function will not do anything.
     *
     * @throws TooManyRequestsHttpException if the limit is reached
     */
    protected function checkRateLimit(Request $request, RateLimiterFactory $rateLimiter, mixed $specifier = ''): void
    {
        $key = $request->getClientIp() . $specifier;
        $limiter = $rateLimiter->create($key);
        if (!$limiter->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(null, 'Too many requests');
        }
    }

    protected function assertLoggedIn(): void
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('', 'Not logged in');
        }
    }

    /**
     * @deprecated This method is deprecated. Use DTOs and #[MapRequestPayload] instead.
     */
    protected function assertThereAreNoValidationErrors(ValidatorInterface $validator, mixed $object): void
    {
        $errors = $validator->validate($object);
        if ($errors->count() > 0) {
            $errors = array_map(
                fn ($error) => ['parameter' => $error->getPropertyPath(), 'error' => $error->getMessage()],
                iterator_to_array($errors)
            );
            throw new BadRequestHttpException(json_encode($errors));
        }
    }

    protected function respondOK(mixed $data = null): Response
    {
        return $this->handleView($this->view($data, Response::HTTP_OK));
    }

    protected function resolveUserId(string $userId): int
    {
        if ($userId === 'current') {
            $this->assertLoggedIn();

            return $this->session->id();
        } elseif (!ctype_digit($userId)) {
            throw new BadRequestHttpException('Invalid userId');
        }

        return (int)$userId;
    }

    protected function normalizeDateToServerTimezone(DateTime $date): Carbon
    {
        $date = Carbon::instance($date);
        $date->setTimezone('Europe/Berlin');

        return $date;
    }
}
