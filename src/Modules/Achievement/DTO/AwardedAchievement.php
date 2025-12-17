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

    public static function createFromArray(array $data): AwardedAchievement
    {
        $awarded = new self();

        $awarded->id = $data['id'];
        $awarded->foodsaverId = $data['foodsaver_id'];
        $awarded->achievementId = $data['achievement_id'];
        $awarded->reviewerId = $data['reviewer_id'];
        $awarded->notice = $data['notice'];
        $awarded->validUntil = isset($data['valid_until']) ? new DateTime($data['valid_until']) : null;
        $awarded->createdAt = new DateTime($data['created_at']);
        $awarded->updatedAt = isset($data['updated_at']) ? new DateTime($data['updated_at']) : null;

        return $awarded;
    }
}
