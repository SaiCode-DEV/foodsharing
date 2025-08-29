<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\CategoryType;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

class CategoriesPermissions
{
    public function __construct(
        private readonly Session $session,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
    ) {
    }

    public function mayEditCategories(CategoryType $type): bool
    {
        switch ($type) {
            case CategoryType::STORE:
                return $this->session->mayRole(Role::ORGA);
            case CategoryType::RESOURCE:
                return $this->currentUserUnits->isAdminFor(RegionIDs::PRODUCT_TEAM);
            default:
                return false;
        }
    }
}
