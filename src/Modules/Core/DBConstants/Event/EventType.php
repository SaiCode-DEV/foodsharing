<?php

namespace Foodsharing\Modules\Core\DBConstants\Event;

enum EventType: int
{
    case OFFLINE = 0;
    case ONLINE = 1;
    case OTHER = 2;
}
