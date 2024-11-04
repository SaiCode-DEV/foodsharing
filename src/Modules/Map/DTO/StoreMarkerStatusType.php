<?php

namespace Foodsharing\Modules\Map\DTO;

enum StoreMarkerStatusType: string
{
    case ALL = 'all';
    case COOPERATING = 'cooperating';
    case NOT_COOPERATING = 'not-cooperating';
}
