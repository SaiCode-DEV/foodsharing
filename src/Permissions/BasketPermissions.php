<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;

class BasketPermissions
{
    private readonly Session $session;

    public function __construct(
        Session $session
    ) {
        $this->session = $session;
    }

    public function mayRequest(int $basket_fs_id): bool
    {
        if ($basket_fs_id != $this->session->id()) {
            return true;
        }

        return false;
    }

    public function mayAdd(): bool
    {
        return $this->session->mayRole();
    }

    public function mayEdit(int $basket_fs_id): bool
    {
        if ($basket_fs_id == $this->session->id()) {
            return true;
        }

        return false;
    }

    public function mayDelete(array $basket): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        if ($basket['foodsaver_id'] === $this->session->id()) {
            return true;
        }

        return false;
    }
}
