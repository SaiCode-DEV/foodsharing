<?php

namespace Foodsharing\Modules\Store\DTO;

use Foodsharing\Modules\Categories\StoreCategoryType;

class StoreStatusForMember
{
    public MinimalStoreIdentifier $store;
    public bool $isManaging;
    public int $membershipStatus;
    public ?int $pickupStatus = null;
    public StoreCategoryType $categoryType;
}
