<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Region\DTO;

class RegionForTreeNavigation extends MinimalRegionIdentifier
{
    public function __construct(
        int $id, string $name,
        public bool $hasChildren,
        public int $type,
    ) {
        parent::__construct($id, $name);
    }
}
