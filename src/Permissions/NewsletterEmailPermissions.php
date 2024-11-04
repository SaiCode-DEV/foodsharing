<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

class NewsletterEmailPermissions
{
    private readonly Session $session;

    public function __construct(
        Session $session,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
    ) {
        $this->session = $session;
    }

    public function mayAdministrateNewsletterEmail(): bool
    {
        return $this->session->mayRole(Role::ORGA) || $this->currentUserUnits->isAdminFor(RegionIDs::NEWSLETTER_WORK_GROUP);
    }
}
