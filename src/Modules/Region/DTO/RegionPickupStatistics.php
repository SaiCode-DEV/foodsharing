<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Region\DTO;

use JMS\Serializer\Annotation\Type;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Include the pickup statistics list of a region for all possible date formats')]
class RegionPickupStatistics
{
    #[OA\Property(
        description: 'Pickup statistics per day',
        type: 'array',
        items: new OA\Items(ref: new Model(type: RegionPickupsPerDate::class))
    )]
    #[Type('array<Foodsharing\Modules\Region\DTO\RegionPickupsPerDate>')]
    public array $daily = [];

    #[OA\Property(
        description: 'Pickup statistics per week',
        type: 'array',
        items: new OA\Items(ref: new Model(type: RegionPickupsPerDate::class))
    )]
    #[Type('array<Foodsharing\Modules\Region\DTO\RegionPickupsPerDate>')]
    public array $weekly = [];

    #[OA\Property(
        description: 'Pickup statistics per month',
        type: 'array',
        items: new OA\Items(ref: new Model(type: RegionPickupsPerDate::class))
    )]
    #[Type('array<Foodsharing\Modules\Region\DTO\RegionPickupsPerDate>')]
    public array $monthly = [];

    #[OA\Property(
        description: 'Pickup statistics per year',
        type: 'array',
        items: new OA\Items(ref: new Model(type: RegionPickupsPerDate::class))
    )]
    #[Type('array<Foodsharing\Modules\Region\DTO\RegionPickupsPerDate>')]
    public array $yearly = [];
}
