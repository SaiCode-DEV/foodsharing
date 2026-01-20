<?php

namespace Foodsharing\Modules\Categories;

/**
 * column `fs_betrieb_kategorie`.`type`:
 * type of the stores assigned to this category
 * INT_TINY UNSIGNED NOT NULL DEFAULT 0 (= PICKUP)
 */
enum StoreCategoryType: int
{
    case PICKUP = 0;
    case GIVING = 1;
    case ORGA = 2;

    public static function isValidStatus(int $status): bool
    {
        return self::tryFrom($status) != null;
    }

    public static function getAllTypes(): array
    {
        return [
            self::PICKUP,
            self::GIVING,
            self::ORGA
        ];
    }
}
