<?php

namespace Foodsharing\Modules\Region\DTO;

use Foodsharing\Modules\Core\DTO\GeoLocation;

/**
 * Represents a region's pin on the map. This can be used for showing it on the map as well as for editing.
 */
class RegionPin extends GeoLocation
{
    public string $description;
    public int $status;

    public static function tryCreate(array $data): ?RegionPin
    {
        if (!isset($data['desc'], $data['lat'], $data['lon'], $data['status'])) {
            return null;
        }
        $pin = new RegionPin();
        $pin->description = $data['desc'];
        $pin->status = $data['status'];
        $pin->lat = floatval($data['lat']);
        $pin->lon = floatval($data['lon']);

        return $pin;
    }
}
