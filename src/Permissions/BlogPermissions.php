<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

final class BlogPermissions
{
    private readonly Session $session;

    public function __construct(Session $session, private readonly CurrentUserUnitsInterface $currentUserUnits)
    {
        $this->session = $session;
    }

    public function mayAdd(): bool
    {
        return $this->mayAdministrateBlog();
    }

    public function mayPublish(int $blogId): bool
    {
        return $this->mayAdd();
    }

    public function mayEdit(?int $blogId): bool
    {
        return $this->mayAdministrateBlog();
    }

    public function mayDelete(int $blogId): bool
    {
        return $this->mayEdit($blogId);
    }

    public function mayAdministrateBlog(): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        return $this->currentUserUnits->isAdminFor(RegionIDs::EDITORIAL_GROUP);
    }
}
