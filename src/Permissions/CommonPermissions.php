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
    /**
     * @var array<int, int[]> Key: user id, Value: array of region ids in which the user is a member
     */
    private array $regionsIdsForUserId = [];

    public function __construct(Session $session, RegionGateway $regionGateway, protected readonly CurrentUserUnitsInterface $currentUserUnits)
    {
        $this->session = $session;
        $this->regionGateway = $regionGateway;
    }

    /**
     * Returns the ids of all regions in which the specified user is. This uses cached values if possible.
     *
     * @return int[]
     */
    private function getCachedUserRegionIds(int $userId): array
    {
        if (!array_key_exists($userId, $this->regionsIdsForUserId)) {
            $this->regionsIdsForUserId[$userId] = $this->regionGateway->getFsRegionIds($userId);
        }

        return $this->regionsIdsForUserId[$userId];
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

        $regionIds = $this->getCachedUserRegionIds($userId);

        return $this->currentUserUnits->isAmbassadorForRegion($regionIds, false, true);
    }
}
