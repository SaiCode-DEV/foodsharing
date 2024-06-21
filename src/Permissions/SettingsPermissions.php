<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
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

    public function mayChangeName(int $userId): bool
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
}
