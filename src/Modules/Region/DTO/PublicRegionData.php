<?php

namespace Foodsharing\Modules\Region\DTO;

use Foodsharing\Modules\Core\DBConstants\Region\RegionPinStatus;
use Foodsharing\Modules\Core\DTO\GeoLocation;
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
    public BasicRegionStatistics $statistics;

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

    public static function tryCreateFrom(array $data): ?PublicRegionData
    {
        $region = new self();
        $region->id = $data['id'];
        $region->name = $data['name'];
        $region->type = $data['type'];
        $region->description = $data['desc'] ?? '';
        if ($data['status'] === RegionPinStatus::ACTIVE) {
            $region->location = GeoLocation::createFromArray($data, false);
        }
        $region->email = $data['email'];
        $region->hasAmbassador = $data['hasAmbassador'];

        return $region;
    }
}
