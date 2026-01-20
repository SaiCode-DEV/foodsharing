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
     * Coordinates of the marker.
     */
    public float $lat;
    public float $lon;

    /**
     * Category Type of the object.
     * May be null for objects without category.
     */
    public ?StoreCategoryType $categoryType = StoreCategoryType::PICKUP;

    public static function createFromArray(array $data): MapMarker
    {
        $marker = new self();
        $marker->id = $data['id'];
        $marker->name = $data['name'] ?? null;
        $marker->lat = round($data['lat'], 6);
        $marker->lon = round($data['lon'], 6);
        $marker->categoryType = StoreCategoryType::tryFrom($data['categoryType'] ?? null) ?? StoreCategoryType::PICKUP;

        return $marker;
    }
}
