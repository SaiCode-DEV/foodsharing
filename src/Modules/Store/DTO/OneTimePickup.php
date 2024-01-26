<?php

namespace Foodsharing\Modules\Store\DTO;

use DateTime;
use DateTimeZone;

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

    public static function createFromArray($queryResult)
    {
        $obj = new OneTimePickup();
        $obj->date = DateTime::createFromFormat('Y-m-d H:i:s', $queryResult['time'], new DateTimeZone('Europe/Berlin'));
        $obj->slots = $queryResult['fetchercount'];
        $obj->description = $queryResult['description'];

        return $obj;
    }
}
