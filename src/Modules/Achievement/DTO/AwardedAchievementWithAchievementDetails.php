<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Achievement\DTO;

use DateTime;
use Foodsharing\Modules\Core\DBConstants\Achievement\DuplicateMode;
use Foodsharing\Modules\Core\DBConstants\Achievement\VisibilityType;
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

    public static function createWithDetails(
        int $id,
        int $regionId,
        string $name,
        string $description,
        ?string $icon,
        ?int $validityInDaysAfterAssignment,
        ?DateTime $createdAt,
        ?DateTime $updatedAt,
        ?string $notice,
        ?DateTime $validUntil,
        VisibilityType $visibilityType,
        DuplicateMode $duplicateMode,
        CommonLabel $scope,
    ): AwardedAchievementWithAchievementDetails {
        $achievement = new self();
        $achievement->id = $id;
        $achievement->regionId = $regionId;
        $achievement->name = $name;
        $achievement->description = $description;
        $achievement->icon = $icon;
        $achievement->validityInDaysAfterAssignment = $validityInDaysAfterAssignment;
        $achievement->createdAt = $createdAt;
        $achievement->updatedAt = $updatedAt;
        $achievement->visibilityType = $visibilityType;
        $achievement->duplicateMode = $duplicateMode;
        $achievement->notice = $notice;
        $achievement->validUntil = $validUntil;
        $achievement->scope = $scope;

        return $achievement;
    }
}
