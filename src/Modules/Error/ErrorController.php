<?php

namespace Foodsharing\Modules\Error;

use Foodsharing\Lib\FoodsharingController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ErrorController extends FoodsharingController
{
    public function __invoke(Throwable $exception, Request $request): Response
    {
        // we don't have a _route attribute here, but PageHelper always expects one for FoodsharingController.
        // inject a dummy value into the request so it does not fail
        $request->attributes->set('_route', 'Error');

        $this->pageHelper->addContent($this->prepareVueComponent('errorbox', 'Error', [
            'code' => $exception instanceof HttpException ? $exception->getStatusCode() : Response::HTTP_INTERNAL_SERVER_ERROR,
        ]));

        return $this->renderGlobal();
    }
}
