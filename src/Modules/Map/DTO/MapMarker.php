<?php

namespace Foodsharing\Modules\Map\DTO;

class MapMarker
{
    /**
     * ID of the object with which the marker is associated.
     */
    public int $id;

    /**
     * Name of the object. Can be displayed as tooltip.
     */
    public ?string $name = null;

    /**
     * Coordinates of the marker.
     */
    public float $lat;
    public float $lon;

    public static function createFromArray(array $data): MapMarker
    {
        $marker = new self();
        $marker->id = $data['id'];
        $marker->name = $data['name'] ?? null;
        $marker->lat = round($data['lat'], 6);
        $marker->lon = round($data['lon'], 6);

        return $marker;
    }
}
