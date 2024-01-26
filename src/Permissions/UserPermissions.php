<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;

final class UserPermissions
{
    private readonly Session $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    public function maySeeUserDetails(int $userId): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        return $userId === $this->session->id();
    }
}
