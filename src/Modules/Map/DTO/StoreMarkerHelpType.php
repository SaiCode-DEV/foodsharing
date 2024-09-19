<?php

namespace Foodsharing\Modules\Map\DTO;

enum StoreMarkerHelpType: string
{
    case ALL = 'all';
    case OPEN = 'open';
    case SEARCHING = 'searching';
}
