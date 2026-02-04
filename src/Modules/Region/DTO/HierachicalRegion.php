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

    public static function createHierachicalRegion(int $id, int $parentId, string $name, string $emailAddress, bool $hasAmbassador): HierachicalRegion
    {
        $region = new HierachicalRegion();
        $region->id = $id;
        $region->name = $name;
        $region->parentId = $parentId;
        $region->emailAddress = $emailAddress;
        $region->hasAmbassador = $hasAmbassador;

        return $region;
    }
}
