<?php

// table fs_store_log

namespace Foodsharing\Modules\Core\DBConstants\Store;

class StoreLogAction
{
    final public const REQUEST_TO_JOIN = 1;
    final public const REQUEST_DECLINED = 2;
    final public const REQUEST_APPROVED = 3;
    final public const ADDED_WITHOUT_REQUEST = 4;
    final public const MOVED_TO_JUMPER = 5;
    final public const MOVED_TO_TEAM = 6;
    final public const REMOVED_FROM_STORE = 7;
    final public const LEFT_STORE = 8;
    final public const APPOINT_STORE_MANAGER = 9;
    final public const REMOVED_AS_STORE_MANAGER = 10;
    final public const SIGN_UP_SLOT = 11;
    final public const SIGN_OUT_SLOT = 12;
    final public const REMOVED_FROM_SLOT = 13;
    final public const SLOT_CONFIRMED = 14;
    final public const DELETED_FROM_WALL = 15;
    final public const REQUEST_CANCELLED = 16;
}
