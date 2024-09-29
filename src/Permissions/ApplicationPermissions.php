<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

class ApplicationPermissions
{
    public function __construct(
        private readonly Session $session,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
    ) {
    }

    public function maySeeApplications(int $groupId): bool
    {
        return $this->session->mayRole(Role::ORGA)
            || $this->currentUserUnits->isAdminFor($groupId);
    }
}
