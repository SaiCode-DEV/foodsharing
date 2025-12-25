<?php

declare(strict_types=1);

namespace Foodsharing\Permissions;

use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

class OAuthPermissions
{
    public function __construct(
        private CurrentUserUnitsInterface $currentUserUnits,
    ) {
    }

    public function mayAdministrateOAuthClients(): bool
    {
        return $this->currentUserUnits->isAdminFor(RegionIDs::OAUTH_CLIENT_ADMINISTRATION_WORK_GROUP);
    }
}
