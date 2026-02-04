<?php

namespace Foodsharing\Modules\Map\DTO;

use Foodsharing\Modules\Categories\StoreCategoryType;

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
     * latitude of the marker.
     */
    public float $lat;

    /**
     * longitude of the marker.
     */
    public float $lon;

    /**
     * Category Type of the object.
     * May be null for objects without category.
     */
    public ?StoreCategoryType $categoryType = StoreCategoryType::PICKUP;

    public static function create(
        int $id,
        ?string $name,
        float $lat,
        float $lon,
        ?StoreCategoryType $categoryType = null
    ): MapMarker {
        $marker = new self();
        $marker->id = $id;
        $marker->name = $name;
        $marker->lat = round($lat, 6);
        $marker->lon = round($lon, 6);
        $marker->categoryType = $categoryType;

        return $marker;
    }
}
