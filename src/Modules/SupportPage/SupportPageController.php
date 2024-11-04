<?php

namespace Foodsharing\Modules\SupportPage;

use Foodsharing\Lib\FoodsharingController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class SupportPageController extends FoodsharingController
{
    public function __construct()
    {
        parent::__construct();
    }

    #[Route(path: '/support', name: 'support_page')]
    public function index(): Response
    {
        $supportPage = $this->prepareVueComponent('support-page', 'SupportPage');
        $this->pageHelper->addContent($supportPage);

        return $this->renderGlobal();
    }
}
