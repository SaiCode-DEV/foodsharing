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
    final public const NOBODY = 0;
    /* special requirements have to be fullfilled in order to apply */
    final public const REQUIRES_PROPERTIES = 1;
    /* everybody can apply for this working group */
    final public const EVERYBODY = 2; // default
    /* the working group is open and does not need application */
    final public const OPEN = 3;
}
