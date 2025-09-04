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
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        if ($this->currentUserUnits->isAdminFor($event->regionId)) {
            return true;
        }

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
