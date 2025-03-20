<?php

namespace Foodsharing\Modules\Store\DTO;

use Foodsharing\Modules\Store\StoreTransactions;
use OpenApi\Annotations as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Describes a regular pickup configuration.
 */
class RegularPickup
{
    /**
     * Weekday of pickup.
     * 0=Sunday, 1=Monday, ...
     *
     * @OA\Property(format="int64", example=1, minimum=0, maximum=6)
     */
    #[Assert\NotBlank]
    #[Assert\Range(min: 0, max: 6, notInRangeMessage: 'Value between {{ min }}=Sunday and {{ max }}=Saturday expected')]
    public int $weekday;

    /**
     * Time of pickup (UTC).
     *
     * @OA\Property(type="string", example="17:20:00")
     */
    #[Assert\NotBlank]
    public string $startTimeOfPickup;

    /**
     * Count of maximum allowed foodsavers for pickup.
     *
     * @OA\Property(type="int", minimum=0, maximum=StoreTransactions::MAX_SLOTS_PER_PICKUP, example=3)
     */
    #[Assert\NotBlank]
    #[Assert\GreaterThanOrEqual(0)]
    public int $maxCountOfSlots;

    /**
     * Description of the pickup slot.
     *
     * @OA\Property(type="string", example="Reserved for new Foodsavers")
     */
    public ?string $description = null;

    public static function createFromArray($query_result)
    {
        $obj = new RegularPickup();
        $obj->startTimeOfPickup = $query_result['time'];
        $obj->weekday = $query_result['dow'];
        $obj->maxCountOfSlots = $query_result['fetcher'];
        $obj->description = $query_result['description'];

        return $obj;
    }
}
