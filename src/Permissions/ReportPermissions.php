<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Report\ReportGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

class ReportPermissions
{
    private readonly Session $session;
    private readonly GroupFunctionGateway $groupFunctionGateway;

    public function __construct(
        Session $session,
        GroupFunctionGateway $groupFunctionGateway,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
        private readonly ReportGateway $reportGateway,
    ) {
        $this->session = $session;
        $this->groupFunctionGateway = $groupFunctionGateway;
    }

    /** Reports list on region level: accessible for orga and for the AMBs of that exact region
     * The reports team has access to the Europe-Region list.
     *
     * from https://gitlab.com/foodsharing-dev/foodsharing/issues/296
     * and https://gitlab.com/foodsharing-dev/foodsharing/merge_requests/529
     */
    public function mayAccessReportsForRegion(int $regionId): bool
    {
        // Orga-role users can access all reports but not necessarily in their
        // own region if they are also an ambassador
        if ($this->session->mayRole(Role::ORGA) && !$this->currentUserUnits->isAmbassadorForRegion([$regionId], false, true)) {
            return true;
        }
        if ($this->isReportAdmin($regionId)) {
            return true;
        }
        if ($this->isArbitrationAdmin($regionId)) {
            return true;
        }

        return false;
    }

    public function isReportAdmin(int $regionId)
    {
        $reportGroup = $this->groupFunctionGateway->getRegionFunctionGroupId($regionId, WorkgroupFunction::REPORT);
        if (!empty($reportGroup)) {
            return $this->currentUserUnits->isAdminFor($reportGroup);
        }

        return false;
    }

    public function isArbitrationAdmin(int $regionId)
    {
        $reportGroup = $this->groupFunctionGateway->getRegionFunctionGroupId($regionId, WorkgroupFunction::ARBITRATION);
        if (!empty($reportGroup)) {
            return $this->currentUserUnits->isAdminFor($reportGroup);
        }

        return false;
    }

    public function mayAccessReportsForUser(int $userId): bool
    {
        if ($this->session->id() === $userId) {
            return false;
        }

        return $this->session->mayRole(Role::ORGA);
    }

    public function mayHandleReports(): bool
    {
        return $this->session->mayRole(Role::ORGA);
    }

    public function mayDeleteReport(int $reportId): bool
    {
        $report = $this->reportGateway->getReportAffiliation($reportId);
        if ($this->session->id() === $report['userId']) {
            return false;
        }

        return $this->session->mayRole(Role::ORGA);
    }
}
