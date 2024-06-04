<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

class ProfilePermissions
{
    private readonly Session $session;
    private readonly CommonPermissions $commonPermissions;
    private readonly FoodsaverGateway $foodsaverGateway;

    public function __construct(Session $session, CommonPermissions $commonPermissions, FoodsaverGateway $foodsaverGateway, private readonly CurrentUserUnitsInterface $currentUserUnits)
    {
        $this->session = $session;
        $this->commonPermissions = $commonPermissions;
        $this->foodsaverGateway = $foodsaverGateway;
    }

    public function hasApplicant(int $userId): bool
    {
        return $this->session->mayRole(Role::STORE_MANAGER) && $this->foodsaverGateway->isApplicant($userId, $this->session->id());
    }

    public function mayEditUserProfile(int $userId): bool
    {
        return $this->session->id() === $userId || $this->commonPermissions->mayAdministrateRegion($userId);
    }

    public function mayCancelSlotsFromProfile(int $userId): bool
    {
        return $this->session->id() != $userId && $this->commonPermissions->mayAdministrateRegion($userId);
    }

    public function mayChangeUserVerification(int $userId): bool
    {
        return $this->commonPermissions->mayAdministrateRegion($userId);
    }

    public function maySeeHistory(int $fsId): bool
    {
        return $this->commonPermissions->mayAdministrateRegion($fsId);
    }

    public function maySeeQuizSessions(): bool
    {
        return $this->session->mayRole(Role::ORGA);
    }

    public function mayDeleteQuizSessions(): bool
    {
        return $this->session->mayRole(Role::ORGA);
    }

    public function maySeeUserNotes(int $userId): bool
    {
        return $this->session->mayRole(Role::ORGA);
    }

    public function maySeePickups(int $fsId): bool
    {
        if (!$this->session->mayRole(Role::FOODSAVER)) {
            return false;
        }

        return $this->maySeeAllPickups($fsId) || $this->commonPermissions->mayAdministrateRegion($fsId);
    }

    public function maySeeAllPickups(int $fsId): bool
    {
        return $this->session->id() == $fsId;
    }

    public function maySeeStores(int $fsId): bool
    {
        if (!$this->session->mayRole(Role::FOODSAVER)) {
            return false;
        }

        return
            $this->session->id() == $fsId ||
            $this->hasApplicant($fsId) ||
            $this->commonPermissions->mayAdministrateRegion($fsId);
    }

    public function maySeeCommitmentsStat(int $fsId): bool
    {
        if ($this->session->id() == $fsId) {
            return true;
        }

        if ($this->commonPermissions->mayAdministrateRegion($fsId)) {
            return true;
        }

        if ($this->session->mayRole(Role::STORE_MANAGER)) {
            if ($this->foodsaverGateway->getCountCommonStores($this->session->id(), $fsId) > 0) {
                return true;
            }
            $getFsID = $this->foodsaverGateway->getFoodsaverBasics($fsId);
            if ($getFsID['bezirk_id'] == $this->currentUserUnits->getCurrentRegionId()) {
                return true;
            }
        }

        return false;
    }

    public function maySeeEmailAddress(int $fsId): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        return $this->session->id() == $fsId;
    }

    public function maySeePrivateEmail(int $userId): bool
    {
        return $this->session->id() === $userId || $this->session->mayRole(Role::ORGA);
    }

    public function maySeelastActivity(int $userId): bool
    {
        return $this->session->mayRole(Role::ORGA);
    }

    public function maySeeRegistrationDate(int $userId): bool
    {
        return $this->session->id() === $userId || $this->session->mayRole(Role::ORGA);
    }

    public function mayDeleteUser(int $userId): bool
    {
        return $this->session->id() == $userId || $this->session->mayRole(Role::ORGA);
    }

    public function maySeeBounceWarning(int $userId): bool
    {
        return $this->session->id() == $userId || $this->mayRemoveFromBounceList($userId);
    }

    public function mayDeleteBanana(int $recipientId): bool
    {
        // users , orga and admin of IT-Support can delete bananas that were given to them by someone else
        return $this->currentUserUnits->isAdminFor(RegionIDs::IT_SUPPORT_GROUP) || $this->session->id() == $recipientId;
    }

    public function mayRemoveFromBounceList(int $userId): bool
    {
        return $this->session->id() == $userId || $this->session->mayRole(Role::ORGA) || $this->currentUserUnits->isAdminFor(RegionIDs::IT_SUPPORT_GROUP);
    }

    public function mayAdministrateUserProfile(int $userId, ?int $regionId = null): bool
    {
        return $this->commonPermissions->mayAdministrateRegion($userId, $regionId);
    }
}
