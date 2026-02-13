<?php

namespace Foodsharing\Modules\Region\DTO;

use Foodsharing\Modules\Core\DTO\GeoLocation;

class PublicRegionPatch
{
    public ?string $description = null;
    public ?GeoLocation $location = null;
    public ?bool $showPin = null;
}
