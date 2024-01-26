<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Group\GroupFunctionGateway;

class FoodSharePointPermissions
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

    public function mayFollow(): bool
    {
        return $this->session->mayRole();
    }

    public function mayUnfollow(int $fspId): bool
    {
        // TODO this should not be allowed if user is manager of the FSP
        return $this->mayFollow();
    }

    public function mayAdd(int $regionId): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        $fspGroup = $this->groupFunctionGateway->getRegionFunctionGroupId($regionId, WorkgroupFunction::FSP);
        if (!empty($fspGroup)) {
            return $this->session->isAdminFor($fspGroup);
        }

        return $this->session->isAdminFor($regionId);
    }

    public function mayEdit(int $regionId, array $follower): bool
    {
        if ($this->mayAdd($regionId)) {
            return true;
        }
        if (isset($follower['all'][$this->session->id()])) {
            return $follower['all'][$this->session->id()] === 'fsp_manager';
        }

        return false;
    }

    public function mayDeleteFoodSharePointOfRegion(int $regionId): bool
    {
        return $this->mayAdd($regionId);
    }

    public function mayApproveFoodSharePointCreation(int $regionId): bool
    {
        return $this->mayAdd($regionId);
    }

    public function mayDeleteFoodSharePointWallPostOfRegion(?int $regionId): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        $fspGroup = $this->groupFunctionGateway->getRegionFunctionGroupId($regionId, WorkgroupFunction::FSP);
        if (!empty($fspGroup)) {
            return $this->session->isAdminFor($fspGroup);
        }

        return false;
    }
}
