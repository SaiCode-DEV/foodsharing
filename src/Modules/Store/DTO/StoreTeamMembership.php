<?php

namespace Foodsharing\Modules\Store\DTO;

use Foodsharing\Modules\Categories\StoreCategoryType;

class StoreTeamMembership
{
    public function __construct(
        public int $id,
        public string $name,
        public bool $isManaging,
        public int $membershipStatus,
        public StoreCategoryType $categoryType = StoreCategoryType::PICKUP
    ) {
    }
}
