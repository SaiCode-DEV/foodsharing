<?php

namespace Foodsharing\Modules\Event;

enum InvitationStatus: int
{
    case INVITED = 0;
    case ACCEPTED = 1;
    case MAYBE = 2;
    case WONT_JOIN = 3;
}
