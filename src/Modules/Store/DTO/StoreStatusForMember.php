<?php

namespace Foodsharing\Modules\Store\DTO;

class StoreStatusForMember
{
    public MinimalStoreIdentifier $store;
    public bool $isManaging;
    public int $membershipStatus;
    public ?int $pickupStatus = null;
}
