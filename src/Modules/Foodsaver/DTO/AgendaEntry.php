<?php

namespace Foodsharing\Modules\Foodsaver\DTO;

use DateTime;

/**
 * Entry in the agenda of a foodsaver.
 * The agenda includes commitments the user has made for a future date, e.g. pickups or events.
 *
 * @see Foodsharing\Modules\Foodsaver\DTO\EventAgendaEntry
 * @see Foodsharing\Modules\Foodsaver\DTO\PickupAgendaEntry
 */
class AgendaEntry
{
    public string $type;
    public int $id;
    public string $name;
    public DateTime $date;

    public function init(string $type, int $id, string $name, DateTime $date)
    {
        $this->type = $type;
        $this->id = $id;
        $this->name = $name;
        $this->date = $date;
    }
}
