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

    /**
     * Creates a Role from an old fAuthorization role name.
     * @throws \Exception for invalid names. Should not happen
     *
     * @deprecated Can be removed with Release M
     */
    public static function fromOldLevelName(string $name): self
    {
        return match ($name) {
            'user' => Role::FOODSHARER,
            'fs' => Role::FOODSAVER,
            'bieb' => Role::STORE_MANAGER,
            'bot' => Role::AMBASSADOR,
            'orga' => Role::ORGA,
            'admin' => Role::SITE_ADMIN,
            default => throw new \Exception('invalid role name')
        };
    }
}
