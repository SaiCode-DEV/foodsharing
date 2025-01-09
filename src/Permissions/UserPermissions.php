<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;

final readonly class UserPermissions
{
    private Session $session;

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
