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
    public Profile $user;
    public ?Profile $reviewer;
    public int $achievementId;
    public ?string $notice;
    public ?DateTime $validUntil;
    public DateTime $createdAt;

    public static function createFromArray(array $data): AwardedAchievementWithUserDetails
    {
        $awarded = new self();
        $awarded->user = Profile::createFromArray($data, 'user_');
        $awarded->reviewer = Profile::createFromArray($data, 'reviewer_');
        $awarded->achievementId = $data['achievement_id'];
        $awarded->notice = $data['notice'];
        $awarded->validUntil = isset($data['valid_until']) ? new DateTime($data['valid_until']) : null;
        $awarded->createdAt = new DateTime($data['created_at']);

        return $awarded;
    }
}
