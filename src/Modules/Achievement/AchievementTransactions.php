<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Achievement;

use Carbon\Carbon;
use Foodsharing\Modules\Achievement\DTO\Achievement;
use Foodsharing\Modules\Achievement\DTO\AwardedAchievement;

class AchievementTransactions
{
    public function __construct(
        private readonly AchievementGateway $achievementGateway,
    ) {
    }

    /**
     * Awards an achievement to a user.
     *
     * @return int the id of the added AwardedAchievement (not the achievement id)
     */
    public function awardAchievement(AwardedAchievement $awardedAchievement, ?Achievement $achievement = null): int
    {
        // TODO: What if an Achievement gets awarded that the user alread has?
        if (!$achievement) {
            $achievement = $this->achievementGateway->getAchievement($awardedAchievement->achievementId);
        }
        $validUntil = $awardedAchievement->validUntil;
        if (!$validUntil && $achievement->validityInDaysAfterAssignment) {
            $validUntil = Carbon::now()->addDays($achievement->validityInDaysAfterAssignment);
        }
        $awardedAchievement->validUntil = $validUntil;

        return $this->achievementGateway->awardAchievement($awardedAchievement);
    }
}
