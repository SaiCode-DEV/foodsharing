<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;

class StoreCategoriesPermissions
{
    private readonly Session $session;

    public function __construct(
        Session $session
    ) {
        $this->session = $session;
    }

    public function mayEditStoreCategories(): bool
    {
        return $this->session->mayRole(Role::ORGA);
    }
}
