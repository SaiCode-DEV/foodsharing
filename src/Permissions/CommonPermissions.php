<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

class CommonPermissions
{
    private readonly Session $session;
    private readonly RegionGateway $regionGateway;

    public function __construct(Session $session, RegionGateway $regionGateway, protected readonly CurrentUserUnitsInterface $currentUserUnits)
    {
        $this->session = $session;
        $this->regionGateway = $regionGateway;
    }

    public function mayAdministrateRegion(int $userId, ?int $regionId = null): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        if (!$this->currentUserUnits->isAmbassador()) {
            return false;
        }

        if ($regionId !== null && $this->currentUserUnits->isAdminFor($regionId)) {
            return true;
        }

        $regionIds = $this->regionGateway->getFsRegionIds($userId);

        return $this->currentUserUnits->isAmbassadorForRegion($regionIds, false, true);
    }
}
