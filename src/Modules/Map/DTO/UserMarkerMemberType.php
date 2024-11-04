<?php

namespace Foodsharing\Modules\Map\DTO;

enum UserMarkerMemberType: string
{
    case ALL = 'all';
    case HOMEREGION = 'homeregion';
    case OTHER = 'other';
}
