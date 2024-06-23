<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

final class AchievementPermissions
{
    public function __construct(
        private readonly Session $session,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
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
}
