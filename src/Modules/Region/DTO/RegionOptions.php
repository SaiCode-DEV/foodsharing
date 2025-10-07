<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Region\DTO;

use Foodsharing\Modules\Core\DBConstants\Region\RegionOptionType;

/**
 * Contains the values of all options that were set for a region. This class correlates with {@see RegionOptionType} and
 * contains the default values for all option types.
 */
class RegionOptions
{
    public bool $isReportButtonEnabled = false;
    public bool $isMediationButtonEnabled = false;
    public bool $isRegionPickupRuleActive = false;
    public int $regionPickupRuleTimespanDays = 0;
    public int $regionPickupRuleLimitNumber = 0;
    public int $regionPickupRuleLimitDayNumber = 0;
    public int $regionPickupRuleInactiveHours = 0;
    public bool $allowHidingInForum = false;
    public int $selectedReportReasonOptions = 1;
    public bool $isReportReasonOtherEnabled = true;
}
