<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

final readonly class RegionPermissions
{
    private RegionGateway $regionGateway;
    private Session $session;
    private GroupFunctionGateway $groupFunctionGateway;

    public function __construct(
        RegionGateway $regionGateway, Session $session, GroupFunctionGateway $groupFunctionGateway,
        private CurrentUserUnitsInterface $currentUserUnits,
        private AchievementPermissions $achievementPermissions,
    ) {
        $this->regionGateway = $regionGateway;
        $this->session = $session;
        $this->groupFunctionGateway = $groupFunctionGateway;
    }

    public function mayJoinRegion(int $regionId): bool
    {
        $type = $this->regionGateway->getType($regionId);

        return $this->session->mayRole(Role::FOODSAVER) && UnitType::isAccessibleRegion($type);
    }

    public function mayAdministrateRegions(): bool
    {
        return $this->session->mayRole(Role::ORGA);
    }

    public function maySetRegionAdmin(): bool
    {
        return $this->session->mayRole(Role::ORGA);
    }

    public function mayRemoveRegionAdmin(): bool
    {
        return $this->session->mayRole(Role::ORGA);
    }

    public function mayAdministrateWorkgroupFunction(int $wgfunction): bool
    {
        if (WorkgroupFunction::isRestrictedWorkgroupFunction($wgfunction)) {
            return $this->session->mayRole(Role::ORGA) && $this->currentUserUnits->isAdminFor(RegionIDs::CREATING_WORK_GROUPS_WORK_GROUP);
        }

        return true;
    }

    public function mayAccessStatisticCountry(): bool
    {
        // Temporarily disabled because it is too inefficient for Europe and Germany
        /* if ($this->session->mayRole(Role::ORGA)) {
            return true;
        } */

        return false;
    }

    public function mayHandleFoodsaverRegionMenu(int $regionId): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        return $this->currentUserUnits->isAmbassadorForRegion([$regionId], false, false);
    }

    public function maySetRegionOptionsReportButtons(int $regionId): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }
        if ($this->groupFunctionGateway->existRegionFunctionGroup($regionId, WorkgroupFunction::REPORT)) {
            if ($this->groupFunctionGateway->isRegionFunctionGroupAdmin($regionId, WorkgroupFunction::REPORT, $this->session->id())) {
                return true;
            }

            return false;
        }

        return $this->currentUserUnits->isAmbassadorForRegion([$regionId], false, false);
    }

    public function maySetRegionOptionsRegionPickupRule(int $regionId): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        if ($this->groupFunctionGateway->existRegionFunctionGroup($regionId, WorkgroupFunction::STORES_COORDINATION)) {
            if ($this->groupFunctionGateway->isRegionFunctionGroupAdmin($regionId, WorkgroupFunction::STORES_COORDINATION, $this->session->id())) {
                return true;
            }

            return false;
        }

        return $this->currentUserUnits->isAmbassadorForRegion([$regionId], false, false);
    }

    public function maySetRegionPin(int $regionId): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        if ($this->groupFunctionGateway->existRegionFunctionGroup($regionId, WorkgroupFunction::PR)) {
            if ($this->groupFunctionGateway->isRegionFunctionGroupAdmin($regionId, WorkgroupFunction::PR, $this->session->id())) {
                return true;
            }

            return false;
        }

        return $this->currentUserUnits->isAmbassadorForRegion([$regionId], false, false);
    }

    public function hasConference(int $regionType): bool
    {
        return in_array($regionType, [UnitType::COUNTRY, UnitType::FEDERAL_STATE, UnitType::CITY, UnitType::WORKING_GROUP, UnitType::PART_OF_TOWN, UnitType::DISTRICT, UnitType::REGION, UnitType::BIG_CITY]);
    }

    public function mayDeleteFoodsaverFromRegion(int $regionId): bool
    {
        return $this->mayHandleFoodsaverRegionMenu($regionId);
    }

    public function maySeeRegionMembers(int $regionId): bool
    {
        /*
         * @TODO: This deactivates member lists for Europe and countries because it needs to much memory on the server.
         * Can be removed when there is pagination for member lists.
         * See also StoreRestController:getStoresOfRegion for the same problem with stores.
         */
        if (in_array($regionId, [RegionIDs::EUROPE, RegionIDs::GERMANY, RegionIDs::AUSTRIA, RegionIDs::SWITZERLAND])) {
            return false;
        }

        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        return $this->currentUserUnits->mayBezirk($regionId);
    }

    public function mayListFoodSharePointsInRegion(int $regionId)
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        return $this->currentUserUnits->mayBezirk($regionId);
    }

    /**
     * Wheter the current user is ambassador of at least one region.
     *
     * This does not account for beein Admin in a working group!
     */
    public function isAmbassadorOfAtLeastOneRegion(): bool
    {
        return $this->regionGateway->isAmbassadorOfAtLeastOneRegion($this->session->id());
    }

    /**
     * Whether the user is allowed to access the list of working groups in a given region.
     */
    public function mayAccessWorkingGroupList(int $regionId): bool
    {
        if (
            $this->session->mayRole(Role::ORGA) ||
            $this->isAmbassadorOfAtLeastOneRegion() ||
            $this->achievementPermissions->mayEditAchievements()
        ) {
            return true;
        }

        return $this->currentUserUnits->mayBezirk($regionId);
    }

    public function mayAccessUserMapMarkersForRegion(int $regionId): bool
    {
        $type = $this->regionGateway->getType($regionId);

        return UnitType::isAccessibleRegion($type) && $this->currentUserUnits->isAdminFor($regionId);
    }
}
