<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Region\DTO;

/**
 * Describes a region by the minimal information.
 *
 * @property int $id Unique identifier of region.
 * @property ?string $name Name of the region.
 */
class MinimalRegionIdentifier
{
    public function __construct(
        public int $id,
        public ?string $name = null,
    ) {
    }
}
