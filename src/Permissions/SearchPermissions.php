<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

class SearchPermissions
{
    private readonly Session $session;
    private readonly GroupFunctionGateway $groupFunctionGateway;
    private readonly RegionPermissions $regionPermissions;

    public function __construct(
        Session $session,
        GroupFunctionGateway $groupFunctionGateway,
        RegionPermissions $regionPermissions,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
    ) {
        $this->session = $session;
        $this->groupFunctionGateway = $groupFunctionGateway;
        $this->regionPermissions = $regionPermissions;
    }

    public function maySearchInRegion(int $regionId): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        return $this->currentUserUnits->mayBezirk($regionId);
    }

    public function maySearchByEmailAddress(): bool
    {
        return $this->maySearchGlobal();
    }

    public function maySearchAllWorkingGroups(): bool
    {
        $privilegedFunctionWorkgroups = [WorkgroupFunction::REPORT, WorkgroupFunction::ARBITRATION, WorkgroupFunction::PR];

        return $this->session->mayRole(Role::ORGA) ||
            $this->regionPermissions->isAmbassadorOfAtLeastOneRegion() ||
            $this->groupFunctionGateway->isAdminForSpecialWorkingGroup($privilegedFunctionWorkgroups, $this->session->id());
    }

    public function maySearchGlobal(): bool
    {
        return $this->session->mayRole(Role::ORGA) || $this->currentUserUnits->isAdminFor(RegionIDs::IT_SUPPORT_GROUP);
    }

    /**
     * Admins of the single überregional food-share-point working group (the Fairteiler-AG) may
     * search and view all food share points platform-wide, not just those in their own regions
     * (#2170). This keys off that one specific group rather than the FSP function, mirroring how
     * maySearchGlobal() keys off the IT support group.
     */
    public function maySearchAllFoodSharePoints(): bool
    {
        return $this->session->mayRole(Role::ORGA) ||
            $this->currentUserUnits->isAdminFor(RegionIDs::FSP_TEAM_ADMIN_GROUP);
    }
}
