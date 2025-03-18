<?php

namespace Foodsharing\Modules\Map\DTO;

use DateTime;
use Foodsharing\Modules\Event\DTO\Event;

class EventMapBubbleData
{
    /**
     * Id of the event.
     */
    public int $id = 0;

    /**
     * Name of the event.
     */
    public string $name = '';

    /**
     * Description of the event in (markdown formatted).
     */
    public string $description;

    /**
     * The date time at which the event starts.
     */
    public DateTime $startDate;

    /**
     * The date time at which the event ends.
     */
    public DateTime $endDate;

    public static function fromEvent(Event $event): EventMapBubbleData
    {
        $data = new self();
        $data->id = $event->id;
        $data->name = $event->name;
        $data->description = $event->description;
        $data->startDate = $event->startDate;
        $data->endDate = $event->endDate;

        return $data;
    }
}
