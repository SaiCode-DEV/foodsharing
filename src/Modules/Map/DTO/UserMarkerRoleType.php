<?php

namespace Foodsharing\Modules\Map\DTO;

enum UserMarkerRoleType: string
{
    case ALL = 'all';
    case FOODSAVER = 'foodsaver';
    case STORE_MANAGER = 'store-manager';
}
