<?php

namespace Foodsharing\Modules\Core\DBConstants\Region;

/**
 * Types of region-specific settings. Corresponds to column 'option_type' in 'fs_region_options'.
 * See {@see RegionGateway::getRegionOption()} and {@see RegionGateway::setRegionOption()}.
 */
class RegionOptionType
{
    final public const int ENABLE_REPORT_BUTTON = 1;
    final public const int ENABLE_MEDIATION_BUTTON = 2;
    final public const int REGION_PICKUP_RULE_ACTIVE = 3; // Is the PickUpRule for this region active ?
    final public const int REGION_PICKUP_RULE_TIMESPAN_DAYS = 4; // What is the timespan for the pickuprule
    final public const int REGION_PICKUP_RULE_LIMIT_NUMBER = 5; // What is the maximum number of pickups a foodsaver is allowed to have during that timespan for stores that follow the regionPickupRule
    final public const int REGION_PICKUP_RULE_LIMIT_DAY_NUMBER = 6; // How many hours before a pickup is the rule being ignored ?
    final public const int REGION_PICKUP_RULE_INACTIVE_HOURS = 7; // How many hours before a pickup is the rule being ignored ?
    final public const int ALLOW_HIDING_IN_FORUM = 10; // Whether moderators are allowed to delete forum posts
    final public const int REPORT_REASON_OPTIONS = 8; // What report Reasons should be shown ? 1 view reasons, 2, Reasons category B)
    final public const int REPORT_REASON_OTHER = 9; // Add reason "other" to report reasons
}
