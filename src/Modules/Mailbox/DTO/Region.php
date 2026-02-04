<?php

namespace Foodsharing\Modules\Mailbox\DTO;

use Foodsharing\Modules\Region\DTO\MinimalRegionIdentifier;

/**
 * Contains information about a region and its email address for use in the mailbox's autocomplete function.
 */
class Region extends MinimalRegionIdentifier
{
    /**
     * Id of the parent of this region.
     */
    public int $parentId;

    /**
     * Type of this region.
     *
     * @see UnitType
     */
    public int $type;

    /**
     * Full email address of the region.
     */
    public string $emailAddress;

    /**
     * Optional name that can be shown instead of the email address. This name might differ from the region's name.
     */
    public ?string $emailName = null;

    public static function createRegion(
        int $id,
        int $parentId,
        string $name,
        int $type,
        string $emailAddress,
        ?string $emailName
    ): Region {
        $region = new Region();

        $region->parentId = $parentId;
        $region->id = $id;
        $region->name = $name;
        $region->type = $type;
        $region->emailAddress = $emailAddress;
        $region->emailName = $emailName;

        return $region;
    }
}
