<?php

// table fs_store_log

namespace Foodsharing\Modules\Core\DBConstants\Store;

class StoreLogAction
{
    final public const int REQUEST_TO_JOIN = 1;
    final public const int REQUEST_DECLINED = 2;
    final public const int REQUEST_APPROVED = 3;
    final public const int ADDED_WITHOUT_REQUEST = 4;
    final public const int MOVED_TO_JUMPER = 5;
    final public const int MOVED_TO_TEAM = 6;
    final public const int REMOVED_FROM_STORE = 7;
    final public const int LEFT_STORE = 8;
    final public const int APPOINT_STORE_MANAGER = 9;
    final public const int REMOVED_AS_STORE_MANAGER = 10;
    final public const int SIGN_UP_SLOT = 11;
    final public const int SIGN_OUT_SLOT = 12;
    final public const int REMOVED_FROM_SLOT = 13;
    final public const int SLOT_CONFIRMED = 14;
    final public const int DELETED_FROM_WALL = 15;
    final public const int REQUEST_CANCELLED = 16;
    final public const int INVITED_TO_TEAM = 17;
    final public const int INVITATION_WITHDRAWN = 18;
    final public const int INVITATION_ACCEPTED = 19;
    final public const int INVITATION_DECLINED = 20;
    final public const int DELETE_STORE = 21;
}
