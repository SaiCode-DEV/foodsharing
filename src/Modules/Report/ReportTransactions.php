<?php

namespace Foodsharing\Modules\Report;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Report\DTO\AddReportData;
use Foodsharing\Modules\Report\DTO\ReportForListView;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReportTransactions
{
    public function __construct(
        private readonly ReportGateway $reportGateway,
        private readonly TranslatorInterface $translator,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly BellGateway $bellGateway,
        private readonly GroupFunctionGateway $groupFunctionGateway,
        private readonly Session $session,
    ) {
    }

    public function addReport(int $reportedId, int $reporterId, AddReportData $reportData): void
    {
        $reasonName = $this->translator->trans($reportData->reason->getTextKey());

        $this->reportGateway->addBetriebReport($reportedId, $reporterId, $reportData, $reasonName);

        $reportedFs = $this->foodsaverGateway->getFoodsaverBasics($reportedId);
        $bellData = Bell::create(
            'new_report_title',
            'report_reason',
            'fas fa-people-arrows fa-fw',
            ['href' => '/report/region/' . $reportedFs['bezirk_id']],
            [
                'name' => $reportedFs['name'] . ' ' . $reportedFs['nachname'],
                'reason' => $reasonName
            ],
            BellType::createIdentifier(BellType::NEW_REPORT, $reportedId),
            true
        );

        $regionReportGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId($reportedFs['bezirk_id'], WorkgroupFunction::REPORT);
        if ($regionReportGroupId) {
            $reportBellRecipients = $this->groupFunctionGateway->getFsAdminIdsFromGroup($regionReportGroupId);
            if (in_array($reportedId, $reportBellRecipients) || in_array($reporterId, $reportBellRecipients)) {
                $regionArbitrationGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId($reportedFs['bezirk_id'], WorkgroupFunction::ARBITRATION);
                $reportBellRecipients = $this->groupFunctionGateway->getFsAdminIdsFromGroup($regionArbitrationGroupId);
            }
            $this->bellGateway->addBellForUsers(array_column($reportBellRecipients, 'id'), $bellData);
        }
    }

    /** @return ReportForListView[] */
    public function getReportsForRegion(int $regionId): array
    {
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

        return $this->reportGateway->getReportsByReporteeRegions($regionId, $excludedIds, $includedIds);
    }
}
