<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Group\GroupFunctionGateway;

class ReportPermissions
{
    private readonly Session $session;
    private readonly GroupFunctionGateway $groupFunctionGateway;

    public function __construct(
        Session $session,
        GroupFunctionGateway $groupFunctionGateway
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
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        $reportGroup = $this->groupFunctionGateway->getRegionFunctionGroupId($regionId, WorkgroupFunction::REPORT);

        if (!empty($reportGroup)) {
            if ($this->session->isAdminFor($reportGroup)) {
                return true;
            }
        }

        $arbitrationGroup = $this->groupFunctionGateway->getRegionFunctionGroupId($regionId, WorkgroupFunction::ARBITRATION);

        if (!empty($arbitrationGroup)) {
            if ($this->session->isAdminFor($arbitrationGroup)) {
                return true;
            }
        }

        return false;
    }

    public function mayAccessArbitrationReports(int $regionId): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        $arbitrationGroup = $this->groupFunctionGateway->getRegionFunctionGroupId($regionId, WorkgroupFunction::ARBITRATION);

        if (!empty($arbitrationGroup)) {
            if ($this->session->isAdminFor($arbitrationGroup)) {
                return true;
            }
        }

        return false;
    }

    public function mayAccessReportGroupReports(int $regionId): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        $reportGroup = $this->groupFunctionGateway->getRegionFunctionGroupId($regionId, WorkgroupFunction::REPORT);

        if (!empty($reportGroup)) {
            if ($this->session->isAdminFor($reportGroup)) {
                return true;
            }
        }

        return false;
    }

    public function mayHandleReports(): bool
    {
        return $this->session->mayRole(Role::ORGA);
    }
}
