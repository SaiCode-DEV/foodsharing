<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Achievement\DTO;

use DateTime;
use Foodsharing\Modules\Store\DTO\CommonLabel;

/**
 * Represents an achievement, that is awarded to a user.
 * This includes the achievement details, so that it holds all data needed to display the awarded achievement in the frontend.
 */
class AwardedAchievementWithAchievementDetails extends Achievement
{
    public ?string $notice = null;
    public ?DateTime $validUntil = null;
    public CommonLabel $scope;

    protected function __construct(array $data)
    {
        parent::__construct($data);
        $this->notice = $data['notice'];
        $this->validUntil = isset($data['valid_until']) ? new DateTime($data['valid_until']) : null;
        $this->createdAt = new DateTime($data['awarded_at']);
        $this->scope = new CommonLabel($data['region_id'], $data['region_name']);
    }

    public static function createFromArray(array $data): AwardedAchievementWithAchievementDetails
    {
        return new self($data);
    }
}
