<?php

namespace Foodsharing\Modules\Foodsaver\DTO;

use DateTime;

/**
 * Entry in the agenda of a foodsaver representing a pickup the user signed up for.
 */
class PickupAgendaEntry extends AgendaEntry
{
    public bool $isConfirmed;

    public static function create(int $id, string $name, DateTime $date, bool $isConfirmed)
    {
        $entry = new self();
        $entry->init('store', $id, $name, $date);
        $entry->isConfirmed = $isConfirmed;

        return $entry;
    }
}
