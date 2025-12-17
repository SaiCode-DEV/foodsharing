<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Achievement;

use Carbon\Carbon;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Achievement\DTO\AwardedAchievement;
use Foodsharing\Modules\Achievement\DTO\AwardedAchievementWithAchievementDetails;
use Foodsharing\Modules\Core\DBConstants\Achievement\DuplicateMode;
use Foodsharing\Modules\Core\DBConstants\Achievement\VisibilityType;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

class AchievementTransactions
{
    public function __construct(
        private readonly AchievementGateway $achievementGateway,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
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
        $awardedAchievement->foodsaverId = $foodsaverId;
        $awardedAchievement->achievementId = $achievementId;
        $awardedAchievement->validUntil = $this->getValidityDateFromNow($achievementId);
        $this->awardAchievement($awardedAchievement);
    }

    public function awardAchievement(AwardedAchievement $awardedAchievement): int
    {
        $achievement = $this->achievementGateway->getAchievement($awardedAchievement->achievementId);
        if ($achievement->duplicateMode === DuplicateMode::OVERRIDE) {
            if ($this->achievementGateway->hasAchievement($awardedAchievement->foodsaverId, $awardedAchievement->achievementId)) {
                $awardedAchievement->id = $this->achievementGateway->getCurrentAwardedAchievementId(
                    $awardedAchievement->foodsaverId,
                    $awardedAchievement->achievementId
                );
                $this->achievementGateway->editAwardedAchievement($awardedAchievement);

                return $awardedAchievement->id;
            }
        }

        return $this->achievementGateway->awardAchievement($awardedAchievement);
    }

    /**
     * Retrieves all awarded achievements for a certain user that are visible to the current user.
     * @return AwardedAchievementWithAchievementDetails[]
     */
    public function getVisibleAwardedAchievementsForUser(int $userId, Session $session): array
    {
        $achievements = $this->achievementGateway->getAwardedAchievementsForUser($userId);
        if ($session->mayRole(Role::ORGA)) {
            return $achievements;
        }

        return array_values(array_filter($achievements, function ($achievement) use ($userId, $session) {
            if ($achievement->visibilityType === VisibilityType::HIDDEN) {
                return false;
            } elseif ($session->id() === $userId) {
                return true;
            }

            return match ($achievement->visibilityType) {
                VisibilityType::PRIVATE => $this->currentUserUnits->isAdminFor($achievement->regionId),
                VisibilityType::STORE_MANAGERS => $session->mayRole(Role::STORE_MANAGER) && $this->currentUserUnits->mayBezirk($achievement->regionId),
                VisibilityType::SCOPE => $this->currentUserUnits->mayBezirk($achievement->regionId),
                // @phpstan-ignore-next-line
                VisibilityType::GLOBAL => true,
                default => false,
            };
        }));
    }
}
