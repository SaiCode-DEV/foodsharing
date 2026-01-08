<?php

namespace Foodsharing\Modules\Group;

class GroupTransactions
{
    public function __construct(
        private readonly GroupGateway $groupGateway,
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
}
