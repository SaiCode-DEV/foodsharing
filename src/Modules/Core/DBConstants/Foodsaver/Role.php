<?php

// table fs_foodsaver

namespace Foodsharing\Modules\Core\DBConstants\Foodsaver;

enum Role: int
{
    case FOODSHARER = 0;
    case FOODSAVER = 1;
    case STORE_MANAGER = 2;
    case AMBASSADOR = 3;
    case ORGA = 4;
    case SITE_ADMIN = 5; // this role is not used currently

    public function isLower(Role $role)
    {
        return $this->value < $role->value;
    }

    public function isAtLeast(Role $role)
    {
        return $this->value >= $role->value;
    }

    public function getRoleName(): string
    {
        return match ($this) {
            Role::FOODSHARER => 'foodsharer',
            Role::FOODSAVER => 'foodsaver',
            Role::STORE_MANAGER => 'storemanager',
            Role::AMBASSADOR => 'ambassador',
            Role::ORGA => 'orga',
            Role::SITE_ADMIN => 'siteadmin',
        };
    }
}
