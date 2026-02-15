<?php

namespace Foodsharing\Modules\Store\DTO;

class StoreTeamMembershipWithPickupStatus extends StoreTeamMembership
{
    public function __construct(
        StoreTeamMembership $membership,
        public ?int $pickupStatus = null
    ) {
        parent::__construct(
            id: $membership->id,
            name: $membership->name,
            isManaging: $membership->isManaging,
            membershipStatus: $membership->membershipStatus,
            categoryType: $membership->categoryType
        );
    }
}
