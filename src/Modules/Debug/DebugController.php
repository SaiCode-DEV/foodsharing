<?php

namespace Foodsharing\Modules\Debug;

use Foodsharing\Lib\FoodsharingController;
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
}
