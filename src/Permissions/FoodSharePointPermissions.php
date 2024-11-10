<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\FoodSharePoint\FollowerType;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\FoodSharePoint\FoodSharePointGateway;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

class FoodSharePointPermissions
{
    public function __construct(
        private readonly Session $session,
        private readonly GroupFunctionGateway $groupFunctionGateway,
        private readonly FoodSharePointGateway $foodSharePointGateway,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
    ) {
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
            return $this->currentUserUnits->isAdminFor($fspGroup);
        }

        return $this->currentUserUnits->isAdminFor($regionId);
    }

    public function mayEdit(int $regionId, array $follower): bool
    {
        $isManager = false;
        if (isset($follower['all'][$this->session->id()])) {
            $isManager = $follower['all'][$this->session->id()] === 'fsp_manager';
        }

        return $this->mayEditFromManager($regionId, $isManager);
    }

    /**
     * Whether the user is allowed to edit a food share point, given if he is a manager of that fsp.
     */
    public function mayEditFromManager(int $regionId, bool $isManager): bool
    {
        if ($this->mayAdd($regionId)) {
            return true;
        }

        return $isManager;
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
            return $this->currentUserUnits->isAdminFor($fspGroup);
        }

        return false;
    }

    public function mayAdministrateFoodSharePoint(int $foodSharePointId): bool
    {
        return $this->foodSharePointGateway->getFollowerStatus($foodSharePointId, $this->session->id()) === FollowerType::FOOD_SHARE_POINT_MANAGER;
    }
}
