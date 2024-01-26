<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;

class SettingsPermissions
{
    private readonly Session $session;

    public function __construct(
        Session $session
    ) {
        $this->session = $session;
    }

    public function mayUseCalendarExport(): bool
    {
        return $this->session->mayRole(Role::FOODSAVER);
    }

    public function mayUsePassportGeneration(): bool
    {
        return $this->session->mayRole(Role::FOODSAVER) && $this->session->isVerified();
    }
}
