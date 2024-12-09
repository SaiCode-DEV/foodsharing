<?php

namespace Foodsharing\Permissions;

use DateTime;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Achievement\AchievementGateway;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;
use Foodsharing\Modules\Core\DBConstants\Achievement\AchievementIDs;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Core\DBConstants\Store\TeamSearchStatus;
use Foodsharing\Modules\Development\FeatureToggles\DependencyInjection\FeatureToggleChecker;
use Foodsharing\Modules\Development\FeatureToggles\Enums\FeatureToggleDefinitions;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Modules\Store\StoreManagerAmount;
use Foodsharing\Modules\Store\TeamStatus as UserTeamStatus;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

class StorePermissions
{
    public function __construct(
        private readonly StoreGateway $storeGateway,
        private readonly Session $session,
        private readonly GroupFunctionGateway $groupFunctionGateway,
        private readonly ProfilePermissions $profilePermissions,
        private readonly RegionGateway $regionGateway,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
        private readonly AchievementGateway $achievementGateway,
        private readonly FeatureToggleChecker $featureToggleChecker,
    ) {
    }

    private function mayIsStoreResponsible($storeId)
    {
        return $this->storeGateway->getUserTeamStatus($this->session->id(), $storeId) === UserTeamStatus::Coordinator;
    }

    /**
     * Assumes that the given user is a foodsaver (i.e. can join store teams).
     * Just the additional permissions for the given, specific store are checked.
     */
    public function mayJoinStoreRequest(int $storeId, ?int $userId = null): bool
    {
        $userId ??= $this->session->id();
        if (is_null($userId)) {
            return false;
        }

        $teamSearchStatus = $this->storeGateway->getStoreTeamStatus($storeId);

        // store open?
        if (!in_array($teamSearchStatus, [TeamSearchStatus::OPEN, TeamSearchStatus::OPEN_SEARCHING])) {
            return false;
        }

        // already in team?
        if ($this->storeGateway->getUserTeamStatus($userId, $storeId) !== UserTeamStatus::NoMember) {
            return false;
        }

        if (
            $this->featureToggleChecker->isFeatureToggleActive(FeatureToggleDefinitions::HYGIENE_QUIZ->value) &&
            $this->storeGateway->getStoreRequiresHygiene($storeId) &&
            !$this->achievementGateway->hasAchievement($userId, AchievementIDs::HYGIENE_CERTIFICATE)
        ) {
            return false;
        }

        return true;
    }

    /**
     * Returns if the current user may add a foodsaver to a store team without having a request from that
     * foodsaver.
     *
     * @param int $storeId the store to which the user shall be added
     * @param int $userId the user to be added
     * @param Role $userRole the role of the user to be added
     */
    public function mayAddUserToStoreTeam(int $storeId, int $userId, Role $userRole): bool
    {
        if (!$this->mayEditStoreTeam($storeId)) {
            return false;
        }
        if ($this->storeGateway->getUserTeamStatus($userId, $storeId) !== UserTeamStatus::NoMember) {
            return false;
        }

        if (!$userRole->isAtLeast(Role::FOODSAVER)) {
            return false;
        }

        // Users can only be added if they are a member of the store's region
        $storeRegionId = $this->storeGateway->getStoreRegionId($storeId);
        $userRegions = array_keys($this->regionGateway->listForFoodsaver($userId));

        return in_array($storeRegionId, $userRegions);
    }

    /**
     * Does not check if the given user is part of the store team.
     * If that is not guaranteed, you will need to verify membership yourself.
     */
    public function mayLeaveStoreTeam(int $storeId, int $userId): bool
    {
        $currentManagers = $this->storeGateway->getStoreManagers($storeId);
        $isManager = in_array($userId, $currentManagers, true);

        return !$isManager;
    }

    public function mayAccessStore(int $storeId): bool
    {
        $fsId = $this->session->id();
        if (!$fsId) {
            return false;
        }

        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }
        if ($this->storeGateway->getUserTeamStatus($fsId, $storeId) >= UserTeamStatus::WaitingList) {
            return true;
        }

        return $this->mayCoordianteRegionStores($storeId);
    }

    public function mayReadStoreWall(int $storeId): bool
    {
        $fsId = $this->session->id();
        if (!$fsId) {
            return false;
        }

        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }
        if ($this->storeGateway->getUserTeamStatus($fsId, $storeId) >= UserTeamStatus::Member) {
            return true;
        }

        return $this->mayCoordianteRegionStores($storeId);
    }

    /**
     * Checks create store permission for current user in session.
     *
     * The method checks the role (StoreManager).
     * If a regionId is provided then a check of the region membership is done.
     *
     * This allows to see if the user is allowed to create store.
     * And of the user is allowed to create stores in the regions belongs to.
     *
     * @param int $regionId Region Id to check membership
     *
     * @return bool true when the user is allowed to create a store
     */
    public function mayCreateStore(?int $regionId = null): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        if ($this->session->mayRole(Role::STORE_MANAGER)) {
            try {
                // Check if user is allowed to create stores in general
                if ($regionId == null) {
                    return true;
                }

                // Check user is allowed to create store in specific region
                return $this->regionGateway->hasMember($this->session->id(), $regionId);
            } catch (DatabaseNoValueFoundException) {
                return false;
            }
        }

        return false;
    }

    public function mayEditStore(int $storeId): bool
    {
        $fsId = $this->session->id();
        if (!$fsId) {
            return false;
        }

        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        if (!$this->session->mayRole(Role::STORE_MANAGER)) {
            return false;
        }

        if (!$this->storeGateway->storeExists($storeId)) {
            return false;
        }

        if ($this->mayIsStoreResponsible($storeId)) {
            return true;
        }

        return $this->mayCoordianteRegionStores($storeId);
    }

    public function mayCoordianteRegionStores(int $storeId): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }
        $storeRegion = $this->storeGateway->getStoreRegionId($storeId);
        $storeGroup = $this->groupFunctionGateway->getRegionFunctionGroupId($storeRegion, WorkgroupFunction::STORES_COORDINATION);
        if (empty($storeGroup)) {
            if ($this->currentUserUnits->isAdminFor($storeRegion)) {
                return true;
            }
        } elseif ($this->currentUserUnits->isAdminFor($storeGroup)) {
            return true;
        }

        return false;
    }

    public function mayEditStoreTeam(int $storeId): bool
    {
        return $this->mayEditStore($storeId);
    }

    public function mayRemovePickupUser(int $storeId, int $fsId): bool
    {
        if ($fsId === $this->session->id()) {
            return true;
        }

        if ($this->mayEditPickups($storeId)) {
            return true;
        }

        if ($this->profilePermissions->mayAdministrateUserProfile($fsId)) {
            return true;
        }

        return false;
    }

    public function mayConfirmPickup(int $storeId): bool
    {
        return $this->mayEditPickups($storeId);
    }

    public function mayEditPickups(int $storeId): bool
    {
        return $this->mayEditStore($storeId);
    }

    public function mayAcceptRequests(int $storeId): bool
    {
        return $this->mayEditStore($storeId);
    }

    public function mayAddPickup(int $storeId): bool
    {
        return $this->mayEditPickups($storeId);
    }

    public function mayDeletePickup(int $storeId): bool
    {
        return $this->mayEditPickups($storeId);
    }

    public function maySeePickupHistory(int $storeId): bool
    {
        return $this->mayEditStore($storeId);
    }

    public function maySeeStoreLog(int $storeId): bool
    {
        return $this->mayEditStore($storeId);
    }

    public function maySeePickupSlotDateTime(int $storeId): bool
    {
        return $this->maySeePickups($storeId);
    }

    public function mayDoPickup(int $storeId, ?DateTime $time = null): bool
    {
        if (!$this->maySeePickups($storeId)) {
            return false;
        }

        if (
            $this->featureToggleChecker->isFeatureToggleActive(FeatureToggleDefinitions::HYGIENE_QUIZ->value) &&
            $this->storeGateway->getStoreRequiresHygiene($storeId) &&
            !$this->achievementGateway->hasAchievement($this->session->id(), AchievementIDs::HYGIENE_CERTIFICATE, $time)
        ) {
            return false;
        }

        return true;
    }

    public function maySeePickups(int $storeId): bool
    {
        if (!$this->session->isVerified()) {
            return false;
        }
        if (!$this->mayReadStoreWall($storeId)) {
            return false;
        }

        return true;
    }

    public function maySeePhoneNumbers(int $storeId): bool
    {
        return $this->maySeePickups($storeId);
    }

    public function mayChatWithRegularTeam(array $store): bool
    {
        if ($store['jumper']) {
            return false;
        }

        return $store['team_conversation_id'] !== null;
    }

    public function mayChatWithJumperWaitingTeam(array $store): bool
    {
        return ($store['verantwortlich'] || $store['jumper']) && $store['springer_conversation_id'] !== null;
    }

    /**
     * This permission roughly assumes that both user and store exist.
     * If that is not guaranteed, you will need to check existence in the callers!
     */
    public function mayBecomeStoreManager(int $storeId, int $userId, Role $userRole): bool
    {
        $currentManagers = $this->storeGateway->getStoreManagers($storeId);

        if (!$this->mayIgnoreStoremanagerCountRestrictions($storeId) && count($currentManagers) >= StoreManagerAmount::MAXIMUM->value) {
            return false;
        }

        $isAlreadyManager = in_array($userId, $currentManagers, true);
        if ($isAlreadyManager) {
            return false;
        }

        return $userRole->isAtLeast(Role::STORE_MANAGER);
    }

    public function mayLoseStoreManagement(int $storeId, int $userId): bool
    {
        $currentManagers = $this->storeGateway->getStoreManagers($storeId);
        $isManager = in_array($userId, $currentManagers, true);
        if (!$isManager) {
            return false;
        }

        // at least one other manager needs to remain after leaving
        if (!$this->mayIgnoreStoremanagerCountRestrictions($storeId) && count($currentManagers) <= StoreManagerAmount::MINIMUM->value) {
            return false;
        }

        return true;
    }

    public function mayIgnoreStoremanagerCountRestrictions(int $storeId): bool
    {
        return $this->mayCoordianteRegionStores($storeId);
    }

    public function maySeePickupOptions(int $userId): bool
    {
        return $this->session->mayRole(Role::FOODSAVER) && $this->session->id() == $userId;
    }

    /**
     * Check is the user have the permission to view the list of stores.
     */
    public function mayListStores(int $userId = null): bool
    {
        if ($userId == null) {
            $userId = $this->session->id();
        }

        if (!$userId) {
            return false;
        }

        return $this->session->mayRole(Role::FOODSAVER);
    }
}
