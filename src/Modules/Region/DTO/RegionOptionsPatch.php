<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Region\DTO;

use Foodsharing\Modules\Core\DBConstants\Region\RegionOptionType;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Contains the values of all options that were set for a region. This class correlates with {@see RegionOptionType}.
 */
class RegionOptionsPatch
{
    public ?bool $isReportButtonEnabled = null;
    public ?bool $isMediationButtonEnabled = null;
    public ?bool $isRegionPickupRuleActive = null;

    #[Assert\PositiveOrZero]
    public ?int $regionPickupRuleTimespanDays = null;

    #[Assert\PositiveOrZero]
    public ?int $regionPickupRuleLimitNumber = null;

    #[Assert\PositiveOrZero]
    public ?int $regionPickupRuleLimitDayNumber = null;

    #[Assert\PositiveOrZero]
    public ?int $regionPickupRuleInactiveHours = null;

    #[Assert\Range(min: 1, max: 2)]
    public ?int $selectedReportReasonOptions = null;
    public ?bool $isReportReasonOtherEnabled = null;
    public ?bool $isAddressChangeNotificationEnabled = null;
}
