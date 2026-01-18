<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Calendar\DTO;

enum IncludeEventsType: string
{
    case EVERY = 'every';
    case INVITATIONS = 'invitations';
    case MAYBE = 'maybe';
    case ACCEPTED = 'accepted';
    case NONE = 'none';
}
