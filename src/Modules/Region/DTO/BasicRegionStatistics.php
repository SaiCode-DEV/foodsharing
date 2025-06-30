<?php

namespace Foodsharing\Modules\Region\DTO;

use DateTime;

/**
 * Some basic statistics of a region. All values include events from the sub-regions.
 */
class BasicRegionStatistics
{
    /**
     * The last time that these statistics were calculated for the correponding region.
     */
    public DateTime $lastUpdated;
    /**
     * Number of verified foodsavers with home region within the region that logged in within the last month.
     */
    public int $activeHomeRegionFoodsavers;

    /**
     * Number of currently cooperating stores.
     */
    public int $activeCoorporations;

    /**
     * Number of filled pickup slots in the last month.
     */
    public int $pickupsLastMonth;

    /**
     * Weight of saved food of the last month. Rounded to full kg.
     */
    public int $savedFoodKgLastMonth;

    /**
     * Number of active food share points.
     */
    public int $activeFoodSharePoints;

    /**
     * Number of foodbaskets in the last month.
     */
    public int $foodBasketsLastMonth;
}
