<?php

namespace Foodsharing\Modules\Region\DTO;

use Foodsharing\Modules\Core\DBConstants\Region\RegionPinStatus;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Event\DTO\EventForListView;
use Foodsharing\Modules\Map\DTO\MapMarker;

class PublicRegionData
{
    public int $id;
    public string $name;
    public int $type;
    public string $description;
    public ?string $email;
    public bool $hasAmbassador;
    public ?GeoLocation $location;
    /**
     * Null if statistics for this region do not exist, e.g. if the region is a working group.
     */
    public ?BasicRegionStatistics $statistics;

    /**
     * @var MinimalRegionIdentifier[]
     */
    public array $ancestors;

    /**
     * @var MinimalRegionIdentifier[]
     */
    public array $children;

    /**
     * @var MapMarker[]
     */
    public array $foodSharePoints;

    /**
     * @var EventForListView[]
     */
    public array $events;

    public static function tryCreateFrom(array $data): ?PublicRegionData
    {
        $region = new self();
        $region->id = $data['id'];
        $region->name = $data['name'];
        $region->type = $data['type'];
        $region->description = $data['desc'] ?? '';
        $region->location = $data['status'] === RegionPinStatus::ACTIVE && !is_null($data['lat']) && !is_null($data['lon'])
            ? new GeoLocation($data['lat'], $data['lon']) : null;
        $region->email = $data['email'];
        $region->hasAmbassador = $data['hasAmbassador'];

        return $region;
    }
}
