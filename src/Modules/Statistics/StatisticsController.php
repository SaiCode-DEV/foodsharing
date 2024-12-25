<?php

namespace Foodsharing\Modules\Statistics;

use Foodsharing\Lib\FoodsharingController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StatisticsController extends FoodsharingController
{
    public function __construct(
    ) {
        parent::__construct();
    }

    #[Route('/statistik', 'statistik')]
    public function index(): Response
    {
        $statistics = $this->prepareVueComponent('statistics', 'Statistics');
        $this->pageHelper->addContent($statistics);

        return $this->renderGlobal();
    }
}
