<?php

// table fs_basket_anfrage

namespace Foodsharing\Modules\Core\DBConstants\BasketRequests;

/**
 * The current status of food basket requests
 * TINYINT(2) UNSIGNED | DEFAULT NULL.
 */
class Status
{
    /* request message sent */
    final public const int REQUESTED_MESSAGE_UNREAD = 0;
    /* request message sent */
    final public const int REQUESTED_MESSAGE_READ = 1;
    /* deleted due to picked up (bell menu) */
    final public const int DELETED_PICKED_UP = 2;
    final public const int DENIED = 3;
    final public const int NOT_PICKED_UP = 4;
    /* deleted due to not picked up or someone else picked up (bell menu) */
    final public const int DELETED_OTHER_REASON = 5;
    /* unused, removed in code, might still be in DB */
    final public const int FOLLOWED = 9;
    /* request pop up opened */
    final public const int REQUESTED = 10;
}
