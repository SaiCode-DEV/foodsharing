<?php

namespace Foodsharing\Modules\Core\DBConstants\FoodSharePoint;

/**
 * Activation status of a food share point.
 *
 * Column `status` in `fs_fairteiler`.
 * TINYINT(4) UNSIGNED NULL DEFAULT NULL
 */
enum ActivationStatus: int
{
    case NOT_ACTIVE = 0;
    case ACTIVE = 1;
}
