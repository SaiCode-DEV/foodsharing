<?php

// table fs_foodsaver

namespace Foodsharing\Modules\Core\DBConstants\Foodsaver;

class Gender
{
    final public const NOT_SELECTED = 0;
    final public const MALE = 1;
    final public const FEMALE = 2;
    final public const DIVERSE = 3;

    /**
     * Returns whether the value is a valid gender constant.
     */
    public static function isValid(int $value): bool
    {
        return in_array($value, range(Gender::NOT_SELECTED, Gender::DIVERSE));
    }
}
