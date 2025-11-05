<?php

namespace Foodsharing\Modules\Debug;

use Foodsharing\Lib\FoodsharingController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DebugController extends FoodsharingController
{
    #[Route('/debug', name: 'debug')]
    public function debug(): Response
    {
        $debug = $this->prepareVueComponent('debug', 'Debug');
        $this->pageHelper->addContent($debug);

        return $this->renderGlobal();
    }

    /**
     * Simple debug API returning server time and HTTPS flag.
     */
    #[Route('/api/debug/server', name: 'api_debug_server', methods: ['GET'])]
    public function debugServer(): JsonResponse
    {
        $data = [
            'time' => date('c'),
            'https' => isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : 'null',
        ];

        return new JsonResponse($data);
    }
}
