<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

class SettingsPermissions
{
    public function __construct(
        private readonly Session $session,
        private readonly RegionGateway $regionGateway,
        private readonly CurrentUserUnitsInterface $currentUserUnitsInterface,
    ) {
    }

    public function mayUseCalendarExport(): bool
    {
        return $this->session->mayRole(Role::FOODSAVER);
    }

    public function mayUsePassportGeneration(): bool
    {
        return $this->session->mayRole(Role::FOODSAVER) && $this->session->isVerified();
    }

    public function mayEditProfileSettings(int $userId): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        if ($this->session->id() === $userId) {
            return true;
        }

        $regionIds = $this->regionGateway->getFsRegionIds($userId);

        if ($this->currentUserUnitsInterface->isAmbassadorForRegion($regionIds, false, true)) {
            return true;
        }

        return false;
    }

    /**
     * Determines if the current user is allowed to change the "no automatic delete after 5 years of inactivity"
     * setting of the profile with the given id. This setting may only ever be changed by the user themselves,
     * not by ORGA, BOT or ambassadors.
     */
    public function mayChangeAutoDeleteSetting(int $userId): bool
    {
        return $this->session->id() === $userId;
    }

    /**
     * Determines if the current user is allowed to change verified data (name, birthdate) of the profile with
     * the given id.
     */
    public function mayChangeVerifiedData(int $userId): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        if ($this->session->role() === Role::FOODSHARER && $this->session->id() === $userId) {
            return true;
        }

        $regionIds = $this->regionGateway->getFsRegionIds($userId);

        if ($this->currentUserUnitsInterface->isAmbassadorForRegion($regionIds, false, true)) {
            return true;
        }

        return false;
    }

    public function mayChangeRole(): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        return false;
    }

    public function mayChangeHomeRegion(int $userId): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        $regionIds = $this->regionGateway->getFsRegionIds($userId);

        if ($this->currentUserUnitsInterface->isAmbassadorForRegion($regionIds, false, true)) {
            return true;
        }

        return false;
    }

    /**
     * Team page data includes the fields "about me public" and "position" in the user data.
     */
    public function mayChangeTeamPageData(int $userId): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        if ($this->session->id() === $userId) {
            return true;
        }

        return false;
    }

    /**
     * Determines if the current user is allowed to change the private (login) email address of the profile with the
     * given id.
     */
    public function mayChangeLoginEmail(int $userId): bool
    {
        return $this->session->mayRole(Role::ORGA)
            && $this->currentUserUnitsInterface->isAmbassadorForRegion([RegionIDs::IT_SUPPORT_GROUP]);
    }

    /**
     * Determines if the current user is allowed to disable 2FA for other users.
     */
    public function mayDisable2FA(): bool
    {
        return $this->session->mayRole(Role::ORGA)
            || $this->currentUserUnitsInterface->isAmbassadorForRegion([RegionIDs::IT_SUPPORT_GROUP]);
    }
}
