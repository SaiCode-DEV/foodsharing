<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Event;

use Foodsharing\Modules\Core\DBConstants\Event\EventType;
use Foodsharing\Modules\Event\DTO\Event;

class EventTransactions
{
    public function __construct(
        private readonly EventGateway $eventGateway,
    ) {
    }

    public function addEvent(int $creatorId, Event $event): int
    {
        $locationId = null;
        if ($event->type === EventType::OFFLINE || $event->type === EventType::OTHER) {
            $locationId = $this->eventGateway->addLocation($event);
        }

        return $this->eventGateway->addEvent($creatorId, $event, $locationId);
    }

    public function editEvent(Event $event): void
    {
        $this->eventGateway->deleteCurrentLocation($event);
        $locationId = null;
        if ($event->type === EventType::OFFLINE || $event->type === EventType::OTHER) {
            $locationId = $this->eventGateway->addLocation($event);
        }
        $this->eventGateway->updateEvent($event, $locationId);
    }
}
