<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\ResourceMosaic\ResourceGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

class ResourcePermissions
{
    public const MAX_RESOURCES_PER_USER = 10;
    public const REGION_TYPES_WITH_RESOURCES = [UnitType::BIG_CITY, UnitType::CITY, UnitType::PART_OF_TOWN, UnitType::WORKING_GROUP];
    private readonly Session $session;

    public function __construct(
        private readonly ResourceGateway $resourceGateway,
        private readonly RegionGateway $regionGateway,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
        private readonly RegionPermissions $regionPermissions,
        Session $session,
    ) {
        $this->session = $session;
    }

    public function maySeeResources(int $regionId, ?int $regionType = null): bool
    {
        if (is_null($regionType)) {
            $regionType = $this->regionGateway->getType($regionId);
        }
        if (!in_array($regionType, self::REGION_TYPES_WITH_RESOURCES)) {
            return false;
        }

        return $this->currentUserUnits->mayBezirk($regionId);
    }

    public function mayAddResource(): bool
    {
        return $this->resourceGateway->getUserResourceCount($this->session->id()) < self::MAX_RESOURCES_PER_USER;
    }

    public function mayDeleteResource(int $ownerId): bool
    {
        return $this->session->id() === $ownerId || $this->session->mayRole(Role::ORGA);
    }

    public function mayEditResource(int $ownerId): bool
    {
        return $this->session->id() === $ownerId;
    }

    public function mayRestrictResourceToRegion(?int $regionId): bool
    {
        if (is_null($regionId)) {
            return true;
        }
        if (!$this->currentUserUnits->mayBezirk($regionId)) {
            return false;
        }

        return in_array($this->regionGateway->getType($regionId), self::REGION_TYPES_WITH_RESOURCES);
    }

    public function mayEditCommonsResourcesInRegion(int $regionId): bool
    {
        return $this->regionPermissions->hasFunctionGroupPermissionForRegion(WorkgroupFunction::RESOURCES, $regionId, true);
    }
}
