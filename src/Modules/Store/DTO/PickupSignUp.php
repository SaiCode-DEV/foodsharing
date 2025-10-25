<?php

namespace Foodsharing\Modules\Store\DTO;

use DateTime;

/**
 * Describes the registration to a pickup by a foodsaver.
 */
class PickupSignUp
{
    /**
     * Date and time of pickup.
     */
    public DateTime $date;

    /**
     * Identifer of foodsaver which confirmed this pickup.
     */
    public int $foodsaverId;

    /**
     * State of the pickup sign up.
     *
     * A foodsaver can confirm the pickup, if he leaves the pickup then state exists but is false.
     */
    public bool $isConfirmed;

    public static function create(DateTime $date, int $foodsaverId, bool $isConfirmed)
    {
        $obj = new PickupSignUp();
        $obj->date = $date;
        $obj->foodsaverId = $foodsaverId;
        $obj->isConfirmed = $isConfirmed;

        return $obj;
    }
}
