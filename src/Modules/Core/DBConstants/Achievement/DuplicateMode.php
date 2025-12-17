<?php

namespace Foodsharing\Modules\Core\DBConstants\Achievement;

/**
 * Defines how to react when a foodsaver receives an achievement that they already have.
 */
enum DuplicateMode: int
{
    /**
     * Only increase the sticker duration to the max of the old and new expiration date, the description is overwritten.
     */
    case OVERRIDE = 0;

    /**
     * The sticker is actually rewarded a second, distinct time.
     */
    case MULTIPLE = 1;
}
