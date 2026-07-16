<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

final readonly class DonationPermissions
{
    public function __construct(
        private Session $session,
        private CurrentUserUnitsInterface $currentUserUnits,
    ) {
    }

    /**
     * Whether the donation page may be edited by the user.
     * @see mayEditDonationPage: make sure that every user for whom `mayEditDonationPage` returns `true`, is in the list returned by that function
     */
    public function mayEditDonationPage(): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        return $this->currentUserUnits->isAdminFor(RegionIDs::FUNDRAISING_AND_FINANCIAL_PLANNING_GROUP);
    }
}
