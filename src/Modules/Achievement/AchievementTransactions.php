<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Achievement;

use Carbon\Carbon;

class AchievementTransactions
{
    public function __construct(
        private readonly AchievementGateway $achievementGateway,
    ) {
    }

    public function getValidityDateFromNow(int $achievementId): ?Carbon
    {
        $achievement = $this->achievementGateway->getAchievement($achievementId);
        if ($achievement->validityInDaysAfterAssignment) {
            return Carbon::now()->addDays($achievement->validityInDaysAfterAssignment);
        }

        return null;
    }
}
