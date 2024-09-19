<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Achievement\AchievementGateway;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

final class AchievementPermissions
{
    public function __construct(
        private readonly Session $session,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
        private readonly AchievementGateway $achievementGateway,
    ) {
    }

    public function mayCreateAchievement(): bool
    {
        return $this->session->mayRole(Role::ORGA);
    }

    public function maySeeAchievementsFromRegion($regionId): bool
    {
        return $this->currentUserUnits->mayBezirk($regionId);
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
        return $userId === $this->session->id() || $this->session->mayRole(Role::ORGA);
    }
}
