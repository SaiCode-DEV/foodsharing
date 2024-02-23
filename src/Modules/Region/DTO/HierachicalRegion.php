<?php

namespace Foodsharing\Modules\Region\DTO;

use Foodsharing\Modules\Mailbox\DTO\Region;

/**
 * Contains information about a region and its children.
 */
class HierachicalRegion extends Region
{
    public array $children = [];
    public bool $hasAmbassador;

    public static function createfromArray(array $data): HierachicalRegion
    {
        $region = new HierachicalRegion();
        $region->id = $data['id'];
        $region->name = $data['name'];
        $region->parentId = $data['parentId'];
        $region->emailAddress = $data['email'];
        $region->hasAmbassador = $data['hasAmbassador'];

        return $region;
    }
}
