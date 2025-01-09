<?php

// table fs_bezirk

namespace Foodsharing\Modules\Core\DBConstants\Unit;

use UnexpectedValueException;

/**
 * the different regions types. First layer are continents
 * TINYINT(4) | NOT NULL DEFAULT '1'.
 */
class UnitType
{
    /* Undefined variable */
    final public const int UNDEFINED = 0;

    /* fourth layer or lower */
    final public const int CITY = 1; // default
    /**
     * fourth layer or lower
     * political region.
     */
    final public const int DISTRICT = 2;
    /**
     * fourth layer or lower
     * geographical region.
     */
    final public const int REGION = 3;
    /**
     * third layer
     * no application (direct member access) possible.
     */
    final public const int FEDERAL_STATE = 5;
    /**
     * second layer
     * no application (direct member access) possible.
     */
    final public const int COUNTRY = 6;
    /* not actually a region and treated differently */
    final public const int WORKING_GROUP = 7;
    /**
     * fourth layer or lower
     * no application (direct member access) possible.
     */
    final public const int BIG_CITY = 8;
    /* fifth layer or lower */
    final public const int PART_OF_TOWN = 9;

    public static function isGroup(int $type): bool
    {
        return in_array($type, UnitType::getGroupTypes());
    }

    public static function isAccessibleRegion(int $type): bool
    {
        return in_array($type, UnitType::getAccessibleRegionTypes());
    }

    public static function isRegion(int $type): bool
    {
        return $type != self::WORKING_GROUP;
    }

    public static function getAccessibleRegionTypes(): array
    {
        return [self::PART_OF_TOWN, self::CITY, self::REGION, self::DISTRICT];
    }

    public static function getRegionTypes(): array
    {
        return [self::PART_OF_TOWN, self::CITY, self::REGION, self::DISTRICT, self::FEDERAL_STATE, self::COUNTRY, self::BIG_CITY];
    }

    public static function getGroupTypes(): array
    {
        return [self::WORKING_GROUP];
    }

    public static function isValid(int $value): bool
    {
        return match ($value) {
            self::PART_OF_TOWN,
            self::BIG_CITY,
            self::WORKING_GROUP,
            self::COUNTRY,
            self::FEDERAL_STATE,
            self::REGION,
            self::DISTRICT,
            self::CITY => true,
            default => false,
        };
    }

    public static function throwIfInvalid(int $value)
    {
        if (!UnitType::isValid($value)) {
            throw new UnexpectedValueException('Unit type is not valid.');
        }
    }
}
