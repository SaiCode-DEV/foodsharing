<?php

namespace Foodsharing\Modules\Map\DTO;

enum UserMarkerActivityType: string
{
    case WEEK = 'week';
    case MONTH = 'month';
    case THREE_MONTHS = '3months';
    case SIX_MONTHS = '6months';
    case ALL = 'all';
}
