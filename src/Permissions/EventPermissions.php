<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

final class EventPermissions
{
    private readonly Session $session;

    public function __construct(Session $session, private readonly CurrentUserUnitsInterface $currentUserUnits)
    {
        $this->session = $session;
    }

    public function mayEditEvent(array $event): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }
        if ($this->currentUserUnits->isAdminFor($event['bezirk_id'])) {
            return true;
        }

        return $event['fs_id'] == $this->session->id();
    }

    public function maySeeEvent(array $event): bool
    {
        return $this->currentUserUnits->mayBezirk($event['bezirk_id']);
    }

    public function mayJoinEvent(array $event): bool
    {
        return $this->maySeeEvent($event);
    }

    public function mayCommentInEvent(array $event): bool
    {
        return $this->maySeeEvent($event);
    }

    public function mayCreateEvent(int $regionId): bool
    {
        return $this->currentUserUnits->mayBezirk($regionId);
    }
}
