<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\ApplyType;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

final readonly class WorkGroupPermissions
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

    public function mayAccess(array $group): bool
    {
        // Workgroup members
        $regionId = $group['id'];
        if ($this->currentUserUnits->mayBezirk($regionId)) {
            return true;
        }
        $groupFunction = $this->groupFunctionGateway->getRegionGroupFunctionId($group['id'], $group['parent_id']);
        if (!is_null($groupFunction) && WorkgroupFunction::isRestrictedWorkgroupFunction($groupFunction)) {
            return false;
        }

        return false;
    }

    public function mayApply(array $group, array $applications, array $stats): bool
    {
        $regionId = $group['id'];
        if (isset($this->currentUserUnits->getRegions()[$regionId])) {
            return false; // may not apply if already a member
        }
        if (in_array($regionId, $applications)) {
            return false; // may not apply if already applied
        }
        if ($group['apply_type'] == ApplyType::EVERYBODY) {
            return true;
        }
        if ($group['apply_type'] == ApplyType::REQUIRES_PROPERTIES) {
            return $this->fulfillApplicationRequirements($group, $stats);
        }

        return false;
    }

    public function mayJoin(array $group): bool
    {
        $regionId = $group['id'];
        if (isset($this->currentUserUnits->getRegions()[$regionId])) {
            return false; // may not join if already a member
        }

        return $group['apply_type'] == ApplyType::OPEN;
    }

    public function fulfillApplicationRequirements(array $group, array $stats): bool
    {
        if ($group['apply_type'] !== ApplyType::REQUIRES_PROPERTIES) {
            return true;
        }

        return
            $stats['bananacount'] >= $group['banana_count']
            && $stats['fetchcount'] >= $group['fetch_count']
            && $stats['weeks'] >= $group['week_num'];
    }
}
