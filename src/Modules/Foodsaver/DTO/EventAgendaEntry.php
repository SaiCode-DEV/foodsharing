<?php

namespace Foodsharing\Modules\Foodsaver\DTO;

use DateTime;

/**
 * Entry in the agenda of a foodsaver representing an event the user might join.
 */
class EventAgendaEntry extends AgendaEntry
{
    public DateTime $end;
    public string $status;

    public static function create(int $id, string $name, DateTime $date, DateTime $end, string $status): self
    {
        $entry = new self();
        $entry->init('event', $id, $name, $date);
        $entry->end = $end;
        $entry->status = $status;

        return $entry;
    }
}
