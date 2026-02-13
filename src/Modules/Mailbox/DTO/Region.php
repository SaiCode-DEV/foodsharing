<?php

namespace Foodsharing\Modules\Mailbox\DTO;

use Foodsharing\Modules\Region\DTO\MinimalRegionIdentifier;

/**
 * Contains information about a region and its email address for use in the mailbox's autocomplete function.
 *
 * @property int $parentId Id of the parent of this region.
 * @property int $type Type of this region.
 * @property string $emailAddress Full email address of the region.
 * @property ?string $emailName Optional name that can be shown instead of the email address. This name might differ from the region's name.
 */
class Region extends MinimalRegionIdentifier
{
    public function __construct(
        int $id, string $name,
        public int $parentId,
        public int $type,
        public string $emailAddress,
        public ?string $emailName = null,
    ) {
        parent::__construct($id, $name);
    }
}
