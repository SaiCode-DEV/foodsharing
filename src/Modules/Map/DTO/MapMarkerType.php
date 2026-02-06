<?php

namespace Foodsharing\Modules\Map\DTO;

enum MapMarkerType: string
{
    case BASKETS = 'baskets';
    case FOOD_SHARE_POINTS = 'foodsharepoints';
    case REGIONS = 'regions';
    case STORES = 'stores';
    case USERS = 'users';
    case EVENTS = 'events';
}
