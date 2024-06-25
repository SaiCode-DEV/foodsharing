<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\Pagination;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Request\ParamFetcher;
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
    protected function checkRateLimit(Request $request, RateLimiterFactory $rateLimiter): void
    {
        $limiter = $rateLimiter->create($request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }
    }

    protected function assertLoggedIn(): void
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('', 'Not logged in');
        }
    }

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

    protected function getPagination(ParamFetcher $paramFetcher, int $maxPageSize = 100): Pagination
    {
        $limit = $paramFetcher->get('limit');
        $offset = $paramFetcher->get('offset');

        foreach (['limit', 'offset'] as $param) {
            if (!is_numeric($$param)) {
                throw new \InvalidArgumentException("The {$param} parameter must be a numeric value.");
            }
            $$param = intval($$param);
            if ($$param < 0) {
                throw new \InvalidArgumentException("The {$param} parameter must non be negative.");
            }
        }
        if ($limit > $maxPageSize) {
            throw new \InvalidArgumentException("The limit parameter must non be larger than {$maxPageSize}.");
        }
        $pagination = new Pagination();
        $pagination->pageSize = $limit;
        $pagination->offset = $offset;

        return $pagination;
    }
}
