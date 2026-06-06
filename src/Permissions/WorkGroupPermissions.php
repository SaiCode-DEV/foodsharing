<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\ApplyType;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

class WorkGroupPermissions
{
    private Session $session;
    private GroupFunctionGateway $groupFunctionGateway;

    public function __construct(
        Session $session,
        GroupFunctionGateway $groupFunctionGateway,
        private CurrentUserUnitsInterface $currentUserUnits,
    ) {
        $this->session = $session;
        $this->groupFunctionGateway = $groupFunctionGateway;
    }

    public function mayEdit(array $group): bool
    {
        // Global orga team
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        $groupFunction = $this->groupFunctionGateway->getRegionGroupFunctionId($group['id'], $group['parent_id']);
        if (!is_null($groupFunction) && WorkgroupFunction::isRestrictedWorkgroupFunction($groupFunction)) {
            return false;
        }

        // Workgroup admins
        $regionId = $group['id'];
        if ($this->currentUserUnits->isAdminFor($regionId)) {
            return true;
        }

        return false;
    }

    public function mayAccess(int $groupId, int $parentId): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }
        if (isset($this->currentUserUnits->getRegions()[$groupId])) {
            return true;
        }

        return false;
    }

    public function mayApply(int $groupId, int $parentId, int $applyType, bool $hasApplied): bool
    {
        if ($hasApplied || isset($this->currentUserUnits->getRegions()[$groupId])) {
            return false; // may not apply if already applied or member
        }
        if ($this->mayAccessGroupList($parentId)) {
            return $applyType === ApplyType::EVERYBODY;
        }

        return false;
    }

    public function mayJoin(int $groupId, int $parentId, int $applyType): bool
    {
        if (isset($this->currentUserUnits->getRegions()[$groupId])) {
            return false; // may not apply if already member
        }
        if ($this->mayAccessGroupList($parentId)) {
            return $applyType === ApplyType::OPEN;
        }

        return false;
    }

    public function mayAccessGroupList(int $regionId): bool
    {
        if ($regionId === RegionIDs::GLOBAL_WORKING_GROUPS) {
            return true;
        }

        return $this->currentUserUnits->mayBezirk($regionId);
    }
}
