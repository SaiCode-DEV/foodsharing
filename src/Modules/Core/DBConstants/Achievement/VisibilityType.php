<?php

namespace Foodsharing\Modules\Core\DBConstants\Achievement;

enum VisibilityType: int
{
    /**
     * Achievement not displayed at all.
     */
    case HIDDEN = 0;

    /**
     * Achievement visible to all admins of scope and owner.
     */
    case PRIVATE = 1;

    /**
     * Achievement visible to all store managers in scope.
     */
    case STORE_MANAGERS = 2;

    /**
     * Achievement visible to all users in scope.
     */
    case SCOPE = 3;

    /**
     * Achievement visible to all users.
     */
    case GLOBAL = 4;
}
