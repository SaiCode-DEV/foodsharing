<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Core\DBConstants\Report\ReportType;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Report\ReportGateway;
use Foodsharing\Permissions\ReportPermissions;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Requirement\Requirement;

class ReportRestController extends AbstractFoodsharingRestController
{
    // literal constants
    private const string NOT_LOGGED_IN = 'not logged in';

    public function __construct(
        protected Session $session,
        private readonly BellGateway $bellGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly ReportGateway $reportGateway,
        private readonly ReportPermissions $reportPermissions,
        private readonly GroupFunctionGateway $groupFunctionGateway
    ) {
        parent::__construct($this->session);
    }

    /**
     * @OA\Tag(name="report")
     *
     * @param int $regionId for which region the reports should be returned
     */
    #[Rest\Get('report/region/{regionId}', requirements: ['regionId' => Requirement::POSITIVE_INT])]
    public function listReportsForRegion(int $regionId): Response
    {
        $this->assertLoggedIn();
        if (!$this->reportPermissions->mayAccessReportsForRegion($regionId)) {
            throw new AccessDeniedHttpException();
        }
        $userId = $this->session->id();
        $excludedIds = [$userId];
        $includedIds = null;
        $reportAdmins = $this->groupFunctionGateway->getFunctionGroupAdminsForRegion($regionId, WorkgroupFunction::REPORT);
        $arbitrationAdmins = $this->groupFunctionGateway->getFunctionGroupAdminsForRegion($regionId, WorkgroupFunction::ARBITRATION);
        $isReportAdmin = in_array($userId, $reportAdmins);
        if ($isReportAdmin) {
            array_push($excludedIds, ...$reportAdmins);
        }
        if (in_array($userId, $arbitrationAdmins)) {
            array_push($excludedIds, ...$arbitrationAdmins);
        }
        if (!($isReportAdmin || $this->session->mayRole(Role::ORGA))) {
            $includedIds = $reportAdmins;
        }

        $reports = $this->reportGateway->getReportsByReporteeRegions($regionId, $excludedIds, $includedIds);

        return $this->respondOK($reports);
    }

    /**
     * @OA\Tag(name="report")
     */
    #[Rest\Get('report/user/{userId}', requirements: ['user' => Requirement::POSITIVE_INT])]
    public function listReportsForUser(int $userId): Response
    {
        $this->assertLoggedIn();
        if (!$this->reportPermissions->mayAccessReportsForUser($userId)) {
            throw new AccessDeniedHttpException();
        }
        $reports = $this->reportGateway->getReportsByUser($userId);

        return $this->handleView($this->view($reports, 200));
    }

    /**
     * Adds a new report. The reportedId must not be empty.
     *
     * @OA\Tag(name="report")
     */
    #[Rest\Post('report')]
    #[Rest\RequestParam(name: 'reportedId', nullable: true)]
    #[Rest\RequestParam(name: 'reporterId', nullable: true)]
    #[Rest\RequestParam(name: 'reasonId', nullable: true)]
    #[Rest\RequestParam(name: 'reason', nullable: true)]
    #[Rest\RequestParam(name: 'message', nullable: true)]
    #[Rest\RequestParam(name: 'storeId', nullable: true)]
    public function addReport(ParamFetcher $paramFetcher): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('', self::NOT_LOGGED_IN);
        }

        $this->reportGateway->addBetriebReport(
            $paramFetcher->get('reportedId'),
            $paramFetcher->get('reporterId'),
            ReportType::LOCAL,
            $paramFetcher->get('reasonId'),
            $paramFetcher->get('reason'),
            $paramFetcher->get('message'),
            $paramFetcher->get('storeId')
        );

        $reportedFs = $this->foodsaverGateway->getFoodsaverBasics($paramFetcher->get('reportedId'));
        $bellData = Bell::create(
            'new_report_title',
            'report_reason',
            'fas fa-people-arrows fa-fw',
            ['href' => '/report/region/' . $reportedFs['bezirk_id']],
            [
                'name' => $reportedFs['name'] . ' ' . $reportedFs['nachname'],
                'reason' => $paramFetcher->get('reason')
            ],
            BellType::createIdentifier(BellType::NEW_REPORT, $reportedFs['id']),
            true
        );

        $reportBellRecipients = 0;
        $regionReportGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId($reportedFs['bezirk_id'], WorkgroupFunction::REPORT);
        if ($regionReportGroupId) {
            $reportBellRecipients = $this->groupFunctionGateway->getFsAdminIdsFromGroup($regionReportGroupId);
            if (in_array($reportedFs['id'], $reportBellRecipients)
             || in_array($paramFetcher->get('reporterId'), $reportBellRecipients)) {
                $regionArbitrationGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId($reportedFs['bezirk_id'], WorkgroupFunction::ARBITRATION);
                $reportBellRecipients = $this->groupFunctionGateway->getFsAdminIdsFromGroup($regionArbitrationGroupId);
            }
            $this->bellGateway->addBell($reportBellRecipients, $bellData);
        }

        return $this->handleView($this->view([$reportBellRecipients], 200));
    }

    #[Rest\Delete('report/{reportId}', requirements: ['reportId' => Requirement::POSITIVE_INT])]
    public function deleteReport(int $reportId): Response
    {
        if (!$this->reportPermissions->mayDeleteReport($reportId)) {
            throw new AccessDeniedHttpException();
        }
        $this->reportGateway->deleteReport($reportId);

        return $this->respondOK();
    }
}
