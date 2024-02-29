<?php

namespace Foodsharing\Modules\Core\DTO;

/**
 * Describes a location by its coordinates, so that a representation on a map is possible.
 */
class GeoLocation
{
    /**
     * Latitude of the location.
     */
    public float $lat = 0;

    /**
     * Longitude of the location.
     */
    public float $lon = 0;

    public static function createFromArray(array $queryResult, $throwInvalidException = true): ?GeoLocation
    {
        $obj = new GeoLocation();
        if (!$throwInvalidException & (is_null($queryResult['lat']) || is_null($queryResult['lon']))) {
            return null;
        }

        if (!is_numeric($queryResult['lat']) || !is_numeric($queryResult['lon'])) {
            if ($throwInvalidException) {
                throw new \InvalidArgumentException('Longitude/Latitude is invalid.');
            } else {
                return null;
            }
        }

        $obj->lat = floatval($queryResult['lat']);
        $obj->lon = floatval($queryResult['lon']);

        return $obj;
    }
}
