<?php

namespace Foodsharing\Modules\Group;

use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Unit\UnitGateway;

class GroupTransactions
{
    public function __construct(
        private readonly GroupGateway $groupGateway,
        private readonly UnitGateway $unitGateway
    ) {
    }

    /**
     * Returns whether the group still contains any sub-regions, stores, or foodsharepoints.
     */
    public function hasSubElements(int $groupId): bool
    {
        $hasRegions = $this->groupGateway->hasSubregions($groupId);
        if ($hasRegions) {
            return true;
        }

        $hasFSPs = $this->groupGateway->hasFoodSharePoints($groupId);
        if ($hasFSPs) {
            return true;
        }

        return $this->groupGateway->hasStores($groupId);
    }

    public function getUserGroups(int $fsId): array
    {
        return $this->unitGateway->listAllDirectReleatedUnitsAndResponsibilitiesOfFoodsaver($fsId, UnitType::getGroupTypes());
    }
}
