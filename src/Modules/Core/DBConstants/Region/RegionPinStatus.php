<?php

namespace Foodsharing\Modules\Core\DBConstants\Region;

/**
 * Status of region pins on the map. Column 'status' in 'fs_region_pin'.
 */
class RegionPinStatus
{
    final public const int INACTIVE = 0;
    final public const int ACTIVE = 1;

    public static function isValid(int $status): bool
    {
        return in_array($status, [self::INACTIVE, self::ACTIVE]);
    }
}
