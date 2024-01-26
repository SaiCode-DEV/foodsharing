<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Region\RegionGateway;

class CommonPermissions
{
    private readonly Session $session;
    private readonly RegionGateway $regionGateway;

    public function __construct(Session $session, RegionGateway $regionGateway)
    {
        $this->session = $session;
        $this->regionGateway = $regionGateway;
    }

    public function mayAdministrateRegion(int $userId, ?int $regionId = null): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        if (!$this->session->isAmbassador()) {
            return false;
        }

        if ($regionId !== null && $this->session->isAdminFor($regionId)) {
            return true;
        }

        $regionIds = $this->regionGateway->getFsRegionIds($userId);

        return $this->session->isAmbassadorForRegion($regionIds, false, true);
    }
}
