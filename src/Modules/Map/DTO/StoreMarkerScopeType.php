<?php

namespace Foodsharing\Modules\Map\DTO;

enum StoreMarkerScopeType: string
{
    case ALL = 'all';
    case REGION = 'region';
    case MEMBER = 'member';
}
