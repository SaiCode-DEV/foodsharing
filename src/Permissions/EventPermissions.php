<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Event\DTO\Event;
use Foodsharing\Modules\Event\EventGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

final readonly class EventPermissions
{
    private Session $session;

    public function __construct(
        Session $session,
        private CurrentUserUnitsInterface $currentUserUnits,
        private EventGateway $eventGateway,
    ) {
        $this->session = $session;
    }

    public function mayEditEvent(Event $event): bool
    {
        // Orga-role users and ambassadors of their region can edit all events
        if ($this->session->mayRole(Role::ORGA) || $this->currentUserUnits->isAdminFor($event->regionId)) {
            return true;
        }

        // Otherwise, only the author of the event can edit it
        return $this->eventGateway->getEventAuthor($event->id) == $this->session->id();
    }

    public function maySeeEvent(Event $event): bool
    {
        return $event->isPublic || $this->currentUserUnits->mayBezirk($event->regionId);
    }

    public function mayJoinEvent(Event $event): bool
    {
        return $this->maySeeEvent($event);
    }

    public function mayCommentInEvent(Event $event): bool
    {
        return $this->maySeeEvent($event) && $this->session->id();
    }

    public function mayCreateEvent(int $regionId): bool
    {
        return $this->currentUserUnits->mayBezirk($regionId);
    }
}
