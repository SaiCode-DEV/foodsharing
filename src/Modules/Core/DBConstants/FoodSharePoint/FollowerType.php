<?php

// table `fs_fairteiler_follower`

namespace Foodsharing\Modules\Core\DBConstants\FoodSharePoint;

/**
 * column `type`
 * follower status for a food share point
 * TINYINT(3)          UNSIGNED NOT NULL DEFAULT '1',.
 */
class FollowerType
{
    final public const int FOLLOWER = 1;
    final public const int FOOD_SHARE_POINT_MANAGER = 2;
}
