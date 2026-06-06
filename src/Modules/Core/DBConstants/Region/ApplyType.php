<?php

// table fs_bezirk

namespace Foodsharing\Modules\Core\DBConstants\Region;

/**
 * only valid for working groups
 * TINYINT(2) | NOT NULL DEFAULT '2'.
 */
class ApplyType
{
    /* no one can apply for this working group */
    final public const int NOBODY = 0;
    /**
     * @deprecated functionality removed
     */
    final public const int REQUIRES_PROPERTIES = 1;
    /* everybody can apply for this working group */
    final public const int EVERYBODY = 2; // default
    /* the working group is open and does not need application */
    final public const int OPEN = 3;
}
