<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Achievement;

use Carbon\Carbon;
use Foodsharing\Modules\Achievement\DTO\AwardedAchievement;

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

    public function awardAchievementFromId(int $achievementId, int $foodsaverId): void
    {
        $awardedAchievement = new AwardedAchievement();
        $awardedAchievement->achievementId = $achievementId;
        $awardedAchievement->foodsaverId = $foodsaverId;
        $awardedAchievement->validUntil = $this->getValidityDateFromNow($achievementId);
        if ($this->achievementGateway->hasAchievement($foodsaverId, $achievementId)) {
            $this->achievementGateway->editAwardedAchievement($awardedAchievement);
        } else {
            $this->achievementGateway->awardAchievement($awardedAchievement);
        }
    }
}
