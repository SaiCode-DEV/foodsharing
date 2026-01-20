<?php

namespace Foodsharing\Modules\Store\DTO;

use Foodsharing\Modules\Categories\StoreCategoryType;

class StoreTeamMembership
{
    public MinimalStoreIdentifier $store;
    public bool $isManaging;
    public int $membershipStatus;
    public StoreCategoryType $categoryType = StoreCategoryType::PICKUP;

    public static function createFromArray(array $query_result): StoreTeamMembership
    {
        $obj = new StoreTeamMembership();
        $obj->store = MinimalStoreIdentifier::createFromArray($query_result, 'store_');
        $obj->isManaging = $query_result['managing'] == 1;
        $obj->membershipStatus = $query_result['membership_status'];
        if (isset($query_result['categoryType'])) {
            $obj->categoryType = StoreCategoryType::tryFrom($query_result['categoryType']) ?? StoreCategoryType::PICKUP;
        }

        return $obj;
    }
}
