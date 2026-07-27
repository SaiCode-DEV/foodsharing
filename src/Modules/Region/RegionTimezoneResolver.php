<?php

namespace Foodsharing\Modules\Region;

use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;

/**
 * Resolves which timezone applies to a region: its own value if set, otherwise the value
 * of the closest ancestor in the region tree, otherwise German time (#2761). Regions
 * without an explicit value inherit, so only non-CET subtrees need the field set.
 */
class RegionTimezoneResolver
{
    final public const string DEFAULT_TIMEZONE = 'Europe/Berlin';

    /** @var array<int, string> timezone names resolved during this request */
    private array $resolved = [];

    public function __construct(
        private readonly Database $db,
    ) {
    }

    public function timezoneFor(?int $regionId): \DateTimeZone
    {
        return new \DateTimeZone($this->timezoneNameFor($regionId));
    }

    public function timezoneNameFor(?int $regionId): string
    {
        if ($regionId === null) {
            return self::DEFAULT_TIMEZONE;
        }

        return $this->resolved[$regionId] ??= $this->resolve($regionId);
    }

    public function timezoneNameForStore(int $storeId): string
    {
        try {
            $regionId = $this->db->fetchValueByCriteria('fs_betrieb', 'bezirk_id', ['id' => $storeId]);
        } catch (DatabaseNoValueFoundException) {
            return self::DEFAULT_TIMEZONE;
        }

        return $this->timezoneNameFor($regionId);
    }

    private function resolve(int $regionId): string
    {
        try {
            // The closure table contains the region itself at depth 0, so an own value
            // wins over inherited ones, and lookup needs no tree walking.
            $timezone = $this->db->fetchValue('
                SELECT b.timezone
                FROM fs_bezirk_closure c
                INNER JOIN fs_bezirk b ON b.id = c.ancestor_id
                WHERE c.bezirk_id = :regionId AND b.timezone IS NOT NULL AND b.timezone != ""
                ORDER BY c.depth ASC
                LIMIT 1
            ', [':regionId' => $regionId]);
        } catch (DatabaseNoValueFoundException) {
            return self::DEFAULT_TIMEZONE;
        }

        // Guard against invalid values in the database; a broken timezone must never
        // take down every page that renders a time.
        if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            return self::DEFAULT_TIMEZONE;
        }

        return $timezone;
    }
}
