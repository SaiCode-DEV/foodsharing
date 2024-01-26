<?php

// tables fs_foodsaver

namespace Foodsharing\Modules\Core\DBConstants\Foodsaver;

/**
 * column `sleep_type`
 * sleep status for a foodsaver
 * TINYINT(3)          UNSIGNED NOT NULL DEFAULT '0',.
 */
class SleepStatus
{
    final public const NONE = 0;
    final public const TEMP = 1;
    final public const FULL = 2;

    public static function isValid(int $mode): bool
    {
        return $mode >= self::NONE && $mode <= self::FULL;
    }
}
