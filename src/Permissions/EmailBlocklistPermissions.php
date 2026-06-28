<?php

declare(strict_types=1);

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;

class EmailBlocklistPermissions
{
    public function __construct(
        private readonly Session $session,
    ) {
    }

    public function mayAdministrateEmailBlocklist(): bool
    {
        return $this->session->mayRole(Role::ORGA);
    }
}
