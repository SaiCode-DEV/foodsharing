<?php

namespace Foodsharing\Modules\Report;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Permissions\ReportPermissions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class ReportController extends FoodsharingController
{
    public function __construct(
        private readonly ReportPermissions $reportPermissions,
        private readonly RegionGateway $regionGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly GroupFunctionGateway $groupFunctionGateway,
    ) {
        parent::__construct();
    }

    #[Route('/report/region/{regionId}', requirements: ['regionId' => Requirement::POSITIVE_INT])]
    public function regionReports(int $regionId): Response
    {
        if (!$this->session->mayRole() || !$this->reportPermissions->mayAccessReportsForRegion($regionId)) {
            return $this->redirect('/');
        }
        try {
            $regionName = $this->regionGateway->getRegionName($regionId);
        } catch (DatabaseNoValueFoundException) {
            return $this->redirectToRoute('dashboard');
        }

        $this->pageHelper->addTitle($regionName);
        $this->pageHelper->addBread($regionName, '/region?bid=' . $regionId);
        $this->pageHelper->addBread($this->translator->trans('reports.reports_region', ['{regionName}' => $regionName]), '/?page=fsbetrieb');

        $regionReportGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId($regionId, WorkgroupFunction::REPORT);

        $this->pageHelper->addContent($this->prepareVueComponent('report-page', 'RegionReportPage', [
            'regionId' => $regionId,
            'regionName' => $regionName,
            'regionReportGroupId' => $regionReportGroupId,
        ]));

        return $this->renderGlobal();
    }

    #[Route('/report/user/{userId}', requirements: ['userId' => Requirement::POSITIVE_INT])]
    public function userReports(int $userId): Response
    {
        if (!$this->session->mayRole() || !$this->reportPermissions->mayAccessReportsForUser($userId)) {
            return $this->redirect('/');
        }
        $userName = $this->foodsaverGateway->getFoodsaverName($userId);

        $this->pageHelper->addContent($this->prepareVueComponent('report-page', 'UserReportPage', [
            'userId' => $userId,
            'userName' => $userName,
        ]));

        return $this->renderGlobal();
    }

    #[Route('/report', name: 'fallback')]
    public function fallback(Request $request): Response
    {
        $regionId = $request->query->getInt('bid', $this->currentUserUnits->getCurrentRegionId() ?? 0);

        return $this->redirect('/report/region/' . $regionId);
    }
}
