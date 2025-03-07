<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Achievement\AchievementGateway;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

class AchievementPermissions
{
    public function __construct(
        private Session $session,
        private CurrentUserUnitsInterface $currentUserUnits,
        private AchievementGateway $achievementGateway,
    ) {
    }

    public function mayEditAchievements(): bool
    {
        // Maybe add a new working group responsible for administrating achievements.
        // For now, just use the creating WG group
        return $this->currentUserUnits->isAdminFor(RegionIDs::CREATING_WORK_GROUPS_WORK_GROUP);
    }

    public function maySeeAchievementsFromRegion($regionId): bool
    {
        return $this->currentUserUnits->mayBezirk($regionId) || $this->mayEditAchievements();
    }

    public function mayAdministrateAchievement(int $achievementId): bool
    {
        $achievement = $this->achievementGateway->getAchievement($achievementId);

        return $this->mayAdministrateAchievementsFromRegion($achievement->regionId);
    }

    public function mayAdministrateAchievementsFromRegion($regionId): bool
    {
        return $this->session->mayRole(Role::ORGA) || $this->currentUserUnits->isAdminFor($regionId);
    }

    public function mayAwardAchievement(int $achievementId, int $userId): bool
    {
        if ($this->session->id() === $userId) {
            return false;
        }

        return $this->mayAdministrateAchievement($achievementId);
    }

    public function maySeeUserAchievements($userId): bool
    {
        return $userId === $this->session->id() || $this->session->mayRole(Role::STORE_MANAGER);
    }
}
