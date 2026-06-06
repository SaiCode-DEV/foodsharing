<?php

namespace Foodsharing\Permissions;

use Foodsharing\Modules\Core\DBConstants\CategoryType;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

class CategoriesPermissions
{
    public function __construct(
        private readonly CurrentUserUnitsInterface $currentUserUnits,
    ) {
    }

    public function mayEditCategories(CategoryType $type): bool
    {
        switch ($type) {
            case CategoryType::STORE:
                return $this->currentUserUnits->isAdminFor(RegionIDs::PRODUCT_TEAM);
            case CategoryType::RESOURCE:
                return $this->currentUserUnits->isAdminFor(RegionIDs::PRODUCT_TEAM);
            case CategoryType::GROUP:
                return $this->currentUserUnits->isAdminFor(RegionIDs::PRODUCT_TEAM);
            default:
                return false;
        }
    }
}
