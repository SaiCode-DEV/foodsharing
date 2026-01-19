<?php

namespace Foodsharing\Modules\Core\DBConstants\Configuration;

enum ConfigurationCategory: int
{
    /**
     * Entries that belong to the donation page.
     */
    case DONATION = 0;
    /**
     * Entries for the daily maintenance and statistics calculation.
     */
    case MAINTENANCE_AND_STATISTICS = 1;
}
