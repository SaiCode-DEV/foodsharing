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
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\PassportGenerator\PassportGeneratorTransaction;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Modules\Store\StoreManagerAmount;
use Foodsharing\Modules\Store\TeamStatus as UserTeamStatus;
use Foodsharing\Modules\StoreChain\StoreChainGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

class StorePermissions
{
    /**
     * Maps store ids to the current user's team status in that store. This only contain data about the logged in user.
     *
     * @see MembershipStatus
     * @var array<int, int> Key: store id, Value: user team membership status
     */
    private array $userTeamStatusCache = [];
    /**
     * @var array<int, int> Key: store id, Value: the store's region id
     */
    private array $storeRegionIdCache = [];

    public function __construct(
        private readonly StoreGateway $storeGateway,
        private readonly Session $session,
        private readonly GroupFunctionGateway $groupFunctionGateway,
        private readonly ProfilePermissions $profilePermissions,
        private readonly RegionGateway $regionGateway,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
        private readonly AchievementGateway $achievementGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly PassportGeneratorTransaction $passportGeneratorTransaction,
        private readonly StoreChainGateway $storeChainGateway,
    ) {
    }

    /**
     * Returns the current user's team status in the specified store. This uses cached values from $userTeamStatusCache
     * if possible.
     */
    private function getCachedUserTeamStatus(int $storeId): int
    {
        if (!array_key_exists($storeId, $this->userTeamStatusCache)) {
            $this->userTeamStatusCache[$storeId] = $this->storeGateway->getUserTeamStatus($this->session->id(), $storeId);
        }

        return $this->userTeamStatusCache[$storeId];
    }

    /**
     * Returns the store's region id. This uses cached values from $storeRegionIdCache if possible.
     */
    private function getCachedStoreRegionId(int $storeId): int
    {
        if (!array_key_exists($storeId, $this->storeRegionIdCache)) {
            $this->storeRegionIdCache[$storeId] = $this->storeGateway->getStoreRegionId($storeId);
        }

        return $this->storeRegionIdCache[$storeId];
    }

    private function mayIsStoreResponsible($storeId)
    {
        return $this->getCachedUserTeamStatus($storeId) === UserTeamStatus::Coordinator;
    }

    /**
     * Assumes that the given user is a foodsaver (i.e. can join store teams).
     * Just the additional permissions for the given, specific store are checked.
     */
    public function mayJoinStoreRequest(int $storeId): bool
    {
        $teamSearchStatus = $this->storeGateway->getStoreTeamStatus($storeId);

        // store open?
        if (!in_array($teamSearchStatus, [TeamSearchStatus::OPEN, TeamSearchStatus::OPEN_SEARCHING])) {
            return false;
        }

        if ($this->getCachedUserTeamStatus($storeId) !== UserTeamStatus::NoMember) {
            return false;
        }

        if (!$this->foodsaverGateway->isProfileComplete($this->session->id())) {
            return false;
        }

        if (!$this->foodsaverGateway->hasHomeRegion($this->session->id())) {
            return false;
        }

        $storeRegionId = $this->getCachedStoreRegionId($storeId);
        if (!$this->regionGateway->hasMember($this->session->id(), $storeRegionId)) {
            return false;
        }

        if ($this->storeGateway->getStoreRequiresVerification($storeId) && !$this->session->isVerified()) {
            return false;
        }

        if ($this->storeGateway->getStoreRequiresPhone($storeId) && !$this->foodsaverGateway->hasPhone($this->session->id())) {
            return false;
        }

        if (
            $this->storeGateway->getStoreRequiresHygiene($storeId) &&
            !$this->achievementGateway->hasAchievement($this->session->id(), AchievementIDs::HYGIENE_CERTIFICATE)
        ) {
            return false;
        }

        return true;
    }

    /**
     * Returns if the current user may invite a foodsaver to a store team without having a request from that
     * foodsaver.
     *
     * @param int $storeId the store to which the user shall be added
     * @param int $userId the user to be added
     * @param Role $userRole the role of the user to be added
     */
    public function mayInviteUserToStoreTeam(int $storeId, int $userId, Role $userRole): bool
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

        return true;
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
        if (in_array($this->getCachedUserTeamStatus($storeId), [UserTeamStatus::WaitingList, UserTeamStatus::Member, UserTeamStatus::Coordinator])) {
            return true;
        }

        if ($this->session->mayRole(Role::STORE_MANAGER) && $this->isKamForStore($storeId)) {
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
        if (in_array($this->getCachedUserTeamStatus($storeId), [UserTeamStatus::Member, UserTeamStatus::Coordinator])) {
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
        $storeRegion = $this->getCachedStoreRegionId($storeId);
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

    public function maySeePickupHistory(int $storeId, ?int $chainId = -1): bool
    {
        return $this->mayEditStore($storeId) || $this->isKamForStore($storeId, $chainId);
    }

    public function maySeeStoreLog(int $storeId): bool
    {
        return $this->mayEditStore($storeId);
    }

    public function maySeePickupSlotDateTime(int $storeId): bool
    {
        return $this->maySeePickups($storeId);
    }

    /**
     * Determines if the current user is allowed to perform a pickup at the specified store and time.
     *
     * @param int $storeId the ID of the store to check permissions for
     * @param DateTime $time the date and time of the pickup
     * @param string $error a reference to a string that will be set with an
     * error message if the user is not allowed to do the pickup
     * @return bool returns if the user is not allowed to do the pickup
     */
    public function mayDoPickup(int $storeId, DateTime $time, string &$error): bool
    {
        if (!$this->maySeePickups($storeId)) {
            $error = 'You do not have permission to see pickups for this store.';

            return false;
        }

        if (
            $this->storeGateway->getStoreRequiresHygiene($storeId) &&
            !$this->achievementGateway->hasAchievement($this->session->id(), AchievementIDs::HYGIENE_CERTIFICATE, $time)
        ) {
            $error = 'You do not have the required hygiene certificate to do pickups or it expires before the pickup.';

            return false;
        }

        // Check if passValidityDate is set and if the date is in the future
        if (!$this->passportGeneratorTransaction->isPassportStillValidAt($this->session->id(), $time)) {
            $error = 'Your passport is not valid for the selected date.';

            return false;
        }

        return true;
    }

    public function maySeePickups(int $storeId): bool
    {
        if (!$this->session->isVerified()) {
            return false;
        }
        if ($this->isKamForStore($storeId)) {
            return true;
        }
        if (!$this->mayReadStoreWall($storeId)) {
            return false;
        }

        return true;
    }

    public function maySeePhoneNumbers(int $storeId): bool
    {
        return $this->mayReadStoreWall($storeId);
    }

    public function maySeeMemberDistance(int $storeId): bool
    {
        return $this->mayEditStore($storeId);
    }

    public function mayChatWithRegularTeam(array $store): bool
    {
        if (!in_array($this->getCachedUserTeamStatus($store['id']), [UserTeamStatus::Member, UserTeamStatus::Coordinator])) {
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

    public function mayLoseStoreManagement(int $storeId, int $userId, string &$errorMessage): bool
    {
        $currentManagers = $this->storeGateway->getStoreManagers($storeId);
        $isManager = in_array($userId, $currentManagers, true);
        if (!$isManager) {
            $errorMessage = 'You are not a manager of this store.';

            return false;
        }

        // at least one other manager needs to remain after leaving
        if (!$this->mayIgnoreStoremanagerCountRestrictions($storeId) && count($currentManagers) <= StoreManagerAmount::MINIMUM->value) {
            $errorMessage = 'You cannot remove the last manager of the store.';

            return false;
        }

        return true;
    }

    public function mayIgnoreStoremanagerCountRestrictions(int $storeId): bool
    {
        return $this->mayCoordianteRegionStores($storeId);
    }

    public function maySeePickupOptions(): bool
    {
        return $this->session->mayRole(Role::FOODSAVER);
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

    public function mayStoreBeDeleted(int $storeId): bool
    {
        if ($this->storeGateway->hasHadPickups($storeId)) {
            return false;
        }

        $latestMessageDate = $this->storeGateway->latestChatMessageDate($storeId);
        $threeMonthsAgo = new DateTime('-3 months');

        return $latestMessageDate < $threeMonthsAgo;
    }

    public function mayDeleteStore($storeId): bool
    {
        return $this->mayEditStore($storeId) && $this->mayStoreBeDeleted($storeId);
    }

    public function isKamForStore(int $storeId, ?int $chainId = -1): bool
    {
        if (is_null($chainId) || $chainId === -1) {
            try {
                $chainId = $this->storeGateway->getStore($storeId, true)->chain->id;
            } catch (\Exception) {
                return false;
            }
        }

        return $this->storeChainGateway->isUserKeyAccountManager($chainId, $this->session->id());
    }
}
