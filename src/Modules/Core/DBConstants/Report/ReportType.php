<?php

namespace Foodsharing\Modules\Core\DBConstants\Report;

/**
 * Can be used in the future to distinguis different types of reports that are handled by different groups.
 * Other examples could be STORE_CHAIN or CROSS_REGIONAL.
 */
enum ReportType: int
{
    case LOCAL = 1; // Regular report, directed at the local report GOALS group
}
