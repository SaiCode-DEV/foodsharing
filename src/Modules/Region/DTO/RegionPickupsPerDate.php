<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Region\DTO;

use JMS\Serializer\Annotation\Type;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Represents one entry in the pickup statistics list of a region')]
class RegionPickupsPerDate
{
    #[OA\Property(description: 'The date range', type: 'string')]
    #[Type('string')]
    public string $date;

    #[OA\Property(description: 'Number of stores in which was picked up', type: 'integer')]
    #[Type('integer')]
    public int $numberOfStores;

    #[OA\Property(description: 'Number of times anything was picked up in any of the stores', type: 'integer')]
    #[Type('integer')]
    public int $numberOfPickups;

    #[OA\Property(description: 'Number of slots in all the pickups combined', type: 'integer')]
    #[Type('integer')]
    public int $numberOfSlots;

    #[OA\Property(description: 'Number of users who were involved in the pickups', type: 'integer')]
    #[Type('integer')]
    public int $numberOfFoodsavers;

    public static function createFromArray(array $data): RegionPickupsPerDate
    {
        $pickups = new RegionPickupsPerDate();
        $pickups->date = $data['time'];
        $pickups->numberOfStores = $data['NumberOfStores'];
        $pickups->numberOfPickups = $data['NumberOfAppointments'];
        $pickups->numberOfSlots = $data['NumberOfSlots'];
        $pickups->numberOfFoodsavers = $data['NumberOfFoodsavers'];

        return $pickups;
    }
}
