<?php

namespace Foodsharing\Modules\Message;

use Foodsharing\Lib\FoodsharingController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MessageController extends FoodsharingController
{
    #[Route('/msg', name: 'msg')]
    public function index(): Response
    {
        if (!$this->session->mayRole()) {
            $this->routeHelper->goLoginAndExit();
        }

        $this->pageHelper->addContent($this->prepareVueComponent('message', 'MessagePage'));

        return $this->renderGlobal('layouts/no-footer.twig');
    }
}
