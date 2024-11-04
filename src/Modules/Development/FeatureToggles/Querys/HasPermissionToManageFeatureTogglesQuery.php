<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Development\FeatureToggles\Querys;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

final class HasPermissionToManageFeatureTogglesQuery
{
    public function execute(Session $session, CurrentUserUnitsInterface $currentUserUnits): bool
    {
        return $session->mayRole(Role::ORGA) || $currentUserUnits->isAdminFor(RegionIDs::IT_AND_SOFTWARE_DEVELOPMENT_GROUP);
    }
}
