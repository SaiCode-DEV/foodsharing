<?php

namespace Foodsharing\Modules\Statistics;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Content\ContentGateway;
use Foodsharing\Modules\Core\DBConstants\Content\ContentId;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StatisticsController extends FoodsharingController
{
    public function __construct(
        private readonly StatisticsView $view,
        private readonly StatisticsGateway $statisticsGateway,
        private readonly ContentGateway $contentGateway,
    ) {
        parent::__construct();
    }

    #[Route('/statistik', 'statistik')]
    public function index(): Response
    {
        $content = $this->contentGateway->get(ContentId::STATISTICS_PAGE);

        $this->pageHelper->addTitle($content['title']);
        $this->pageHelper->addBread($content['title']);

        $stat_total = $this->statisticsGateway->listTotalStat();
        $stat_total['totalBaskets'] = $this->statisticsGateway->countAllBaskets();
        $stat_total['avgWeeklyBaskets'] = $this->statisticsGateway->avgWeeklyBaskets();

        $stat_regions = $this->statisticsGateway->listStatRegions();
        $stat_fs = $this->statisticsGateway->listStatFoodsaver();

        $this->pageHelper->addContent($this->view->getStatTotal(
            $stat_total,
            $this->statisticsGateway->countAllFoodsharers(),
            $this->statisticsGateway->avgDailyFetchCount(),
            $this->statisticsGateway->countActiveFoodSharePoints()
        ), CNT_TOP);
        $this->pageHelper->addContent($this->view->getStatRegions($stat_regions), CNT_LEFT);
        $this->pageHelper->addContent($this->view->getStatFoodsaver($stat_fs), CNT_RIGHT);

        $this->pageHelper->setContentWidth(12, 12);

        return $this->renderGlobal();
    }
}
