<?php

// table fs_buddy

namespace Foodsharing\Modules\Core\DBConstants\Buddy;

/**
 * column `buddy_id`
 * IDs for buddy request states
 * INT(10) UNSIGNED NOT NULL.
 */
class BuddyId
{
    final public const int NO_BUDDY = -1;
    final public const int REQUESTED = 0;
    final public const int BUDDY = 1;
}
