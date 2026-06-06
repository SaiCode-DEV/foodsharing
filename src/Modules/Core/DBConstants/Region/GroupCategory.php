<?php

namespace Foodsharing\Modules\Core\DBConstants\Region;

enum GroupCategory: int
{
    case DEVELOPMENT = 1;
    case EXCHANGE = 2;
    case ADMINISTRATIVE = 3;
    case PROJECT = 4;
    case ARCHIVED = 5;
}
