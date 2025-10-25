<?php

namespace Foodsharing\Modules\Store\DTO;

use DateTime;

/**
 * Describes a one time pickup at store.
 */
class OneTimePickup
{
    /**
     * Date and time of pickup.
     */
    public DateTime $date;

    /**
     * Count of slots for pickup.
     */
    public int $slots;

    /**
     * Description of a pickup.
     */
    public ?string $description = null;

    public static function create(DateTime $date, int $slots, ?string $description): OneTimePickup
    {
        $obj = new OneTimePickup();
        $obj->date = $date;
        $obj->slots = $slots;
        $obj->description = $description;

        return $obj;
    }
}
