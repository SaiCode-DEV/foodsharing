<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Achievement\DTO;

use DateTime;
use Foodsharing\Modules\Foodsaver\Profile;

/**
 * Represents the detail of an achievement being awarded to a user.
 * The achievement itself gets only represented by its id.
 */
class AwardedAchievementWithUserDetails
{
    public int $id;
    public Profile $user;
    public ?Profile $reviewer = null;
    public int $achievementId;
    public ?string $notice = null;
    public ?DateTime $validUntil = null;
    public DateTime $createdAt;

    public static function create(
        int $id,
        Profile $user,
        ?Profile $reviewer,
        int $achievementId,
        ?string $notice,
        ?DateTime $validUntil,
        DateTime $createdAt
    ): self {
        $awardedAchievement = new self();

        $awardedAchievement->id = $id;
        $awardedAchievement->user = $user;
        $awardedAchievement->reviewer = $reviewer;
        $awardedAchievement->achievementId = $achievementId;
        $awardedAchievement->notice = $notice;
        $awardedAchievement->validUntil = $validUntil;
        $awardedAchievement->createdAt = $createdAt;

        return $awardedAchievement;
    }
}
