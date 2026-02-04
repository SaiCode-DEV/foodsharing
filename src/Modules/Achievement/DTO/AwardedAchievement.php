<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Achievement\DTO;

use DateTime;

/**
 * Represents the detail of an achievement being awarded to a user.
 * The achievement itself gets only represented by its id.
 */
class AwardedAchievement
{
    /**
     * ID of the RewardedAchievement. Not the ID of the achievement.
     */
    public int $id;
    public int $foodsaverId;
    public int $achievementId;

    /**
     * ID of the foodsaver who reviewed this awarding.
     * null if it was awarded without reviewer.
     */
    public ?int $reviewerId = null;

    public ?string $notice = null;
    public ?DateTime $validUntil = null;
    public ?DateTime $createdAt = null;
    public ?DateTime $updatedAt = null;

    public static function create(
        int $id,
        int $foodsaverId,
        int $achievementId,
        ?int $reviewerId = null,
        ?string $notice = null,
        ?DateTime $validUntil = null,
        ?DateTime $createdAt = null,
        ?DateTime $updatedAt = null
    ): AwardedAchievement {
        $achievement = new self();
        $achievement->id = $id;
        $achievement->foodsaverId = $foodsaverId;
        $achievement->achievementId = $achievementId;
        $achievement->reviewerId = $reviewerId;
        $achievement->notice = $notice;
        $achievement->validUntil = $validUntil;
        $achievement->createdAt = $createdAt;
        $achievement->updatedAt = $updatedAt;

        return $achievement;
    }
}
