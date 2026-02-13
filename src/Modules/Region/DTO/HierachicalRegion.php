<?php

namespace Foodsharing\Modules\Region\DTO;

use Foodsharing\Modules\Mailbox\DTO\Region;

/**
 * Contains information about a region and its children.
 */
class HierachicalRegion extends Region
{
    public array $children = [];

    public function __construct(
        int $id, string $name, int $parentId, int $type, string $emailAddress,
        public bool $hasAmbassador,
    ) {
        parent::__construct($id, $name, $parentId, $type, $emailAddress);
    }
}
