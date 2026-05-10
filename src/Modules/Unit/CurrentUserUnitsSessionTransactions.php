<?php

namespace Foodsharing\Modules\Unit;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Achievement\AchievementGateway;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Region\RegionGateway;

/**
 * Internal class to store unit (region, groups, working groups) relationship information structured into session.
 *
 * This class is stored inside of session by serialization.
 */
class UserUnitsInformation
{
    /**
     * List of units where the user is admin/ambassador.
     */
    public array $unitsWithAdminMembership = [];

    /**
     * List of units where the user is member (independent of relation).
     */
    public array $unitsWithMembership = [];

    /**
     * Home region of the user.
     */
    public ?int $homeRegionId = null;
}

/**
 * This class provides unit check functions based on information which are stored in the user session.
 */
class CurrentUserUnitsSessionTransactions implements CurrentUserUnitsInterface
{
    private const string SESSION_FIELD_NAME = 'units_information';

    public function __construct(
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly RegionGateway $regionGateway,
        private readonly AchievementGateway $achievementGateway,
        private readonly Session $session
    ) {
    }

    /** Provides unit relation information from database.
     *
     * Addional it checks and fix membership relations for the home region and home master region.
     *
     * @return UserUnitsInformation|null Collection of user related unit informations from database
     */
    private function loadUnitsInformation(): ?UserUnitsInformation
    {
        $fsId = $this->session->id();

        if ($fsId === null) {
            return null;
        }

        $informations = new UserUnitsInformation();
        if ($this->session->role()->isAtLeast(Role::FOODSAVER)) {
            $informations->homeRegionId = $this->foodsaverGateway->getHomeRegionOfFoodsaver($fsId);

            // Correct membership if homeregion is set
            if ($informations->homeRegionId) {
                $this->regionGateway->addMember($fsId, $informations->homeRegionId);

                // Correct membership if master region
                if ($master = $this->regionGateway->getMasterId($informations->homeRegionId)) {
                    $this->regionGateway->addMember($fsId, $master);
                }
            }

            if ($units = $this->regionGateway->listRegionsForBotschafter($fsId)
            ) {
                $informations->unitsWithAdminMembership = $units;

                // Correct membership
                foreach ($units as $unit) {
                    $this->regionGateway->addOrUpdateMember($fsId, $unit['id']);
                }
            }

            $informations->unitsWithMembership = $this->regionGateway->listForFoodsaver($fsId);
            foreach ($informations->unitsWithMembership as &$region) {
                $region['hasAchievements'] = $this->achievementGateway->regionHasAchievements($region['id']);
            }
        }

        return $informations;
    }

    /**
     * Provides the unit relation information from session and initializes the session if necessary.
     *
     * The session is used as cache so that not every access requires to load data from database.
     *
     * @param bool $forceLoad Force to load information from database
     * @return UserUnitsInformation|null Collection of user related unit informations
     */
    private function getOrFetchUserUnitsInformation(bool $forceLoad = false): ?UserUnitsInformation
    {
        $unitsInformation = null;
        if (!$forceLoad && $this->session->has(CurrentUserUnitsSessionTransactions::SESSION_FIELD_NAME)) {
            $unitsInformation = unserialize($this->session->get(CurrentUserUnitsSessionTransactions::SESSION_FIELD_NAME), ['allowed_classes' => true]);
        }

        if (!$unitsInformation) {
            $unitsInformation = $this->loadUnitsInformation();
            if ($unitsInformation) {
                $this->session->set(CurrentUserUnitsSessionTransactions::SESSION_FIELD_NAME, serialize($unitsInformation));
            }
        }

        return $unitsInformation;
    }

    public function getCurrentRegionId(): ?int
    {
        return $this->getOrFetchUserUnitsInformation()->homeRegionId ?? null;
    }

    public function getRegions(): array
    {
        return $this->getOrFetchUserUnitsInformation()->unitsWithMembership ?? [];
    }

    public function listRegionIDs(): array
    {
        return array_map(fn ($unit) => $unit['id'], $this->getRegions());
    }

    public function getMyAmbassadorRegionIds(bool $includeWorkingGroups = true): array
    {
        $managedUnits = $this->getOrFetchUserUnitsInformation()->unitsWithAdminMembership ?? [];

        if (!$includeWorkingGroups) {
            $managedUnits = array_filter($managedUnits, fn ($unit) => !UnitType::isGroup($unit['type']));
        }

        return array_map(fn ($unit) => $unit['bezirk_id'], $managedUnits);
    }

    public function isAdminFor(?int $regionId): bool
    {
        $managedUnits = $this->getOrFetchUserUnitsInformation()->unitsWithAdminMembership ?? [];
        if ($regionId) {
            $managedUnits = array_filter($managedUnits, fn ($unit) => $unit['bezirk_id'] == $regionId);
        }

        return count($managedUnits) > 0;
    }

    private function isMemberFor(?int $regionId): bool
    {
        $units = $this->getOrFetchUserUnitsInformation()->unitsWithMembership ?? [];
        $searchUnits = array_filter($units, fn ($unit) => $unit['id'] == $regionId);

        return count($searchUnits) > 0;
    }

    public function isAmbassador(): bool
    {
        return count($this->getOrFetchUserUnitsInformation()->unitsWithAdminMembership ?? []) > 0;
    }

    public function mayBezirk(int $regionId): bool
    {
        // Users that are not logged in don't have a role we could compare to
        if ($this->session->role() === null || $this->session->id() === null) {
            return false;
        }

        if ($this->session->role()->isAtLeast(Role::ORGA)) {
            return true;
        }
        // use database check if the session includes the region to unsure previleges are lost after removal from a region
        $isMember = $this->isMemberFor($regionId);
        if ($isMember && !$this->regionGateway->hasMember($this->session->id(), $regionId)) {
            // Reload session content to ensure valid membership
            $this->getOrFetchUserUnitsInformation(true);
            $isMember = false;
        }

        return $isMember;
    }

    /**
     * Checks if the current user is an ambassador for one of the units in the list of region IDs.
     *
     * @param array $regionIds list of region IDs
     * @param bool $include_groups if working group should be included in the check
     * @param bool $include_parent_regions if the parent units should be included in the check
     */
    public function isAmbassadorForRegion($regionIds, $include_groups = true, $include_parent_regions = false): bool
    {
        if (is_array($regionIds) && count($regionIds) && $this->isAmbassador()) {
            if ($include_parent_regions) {
                $regionIds = $this->regionGateway->listRegionsIncludingParents($regionIds);
            }
            $unitsWithAdminRelation = $this->getOrFetchUserUnitsInformation()->unitsWithAdminMembership ?? [];
            foreach ($unitsWithAdminRelation as $unit) {
                foreach ($regionIds as $unitId) {
                    $consider = $include_groups || UnitType::isRegion($unit['type']);
                    if ($consider && $unit['bezirk_id'] == $unitId) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Clears the cached units information in all sessions of this user so
     * regions/home-region are reloaded on next access.
     */
    public function clearUnitsInformation(): void
    {
        $this->session->clearSessionFieldForUser($this->session->id(), CurrentUserUnitsSessionTransactions::SESSION_FIELD_NAME);
    }
}
