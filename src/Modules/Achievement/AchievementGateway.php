<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Achievement;

use Carbon\Carbon;
use Foodsharing\Modules\Achievement\DTO\Achievement;
use Foodsharing\Modules\Achievement\DTO\AwardedAchievement;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;

class AchievementGateway extends BaseGateway
{
    public function __construct(
        Database $db,
    ) {
        parent::__construct($db);
    }

    /**
     * Adds a given achievement to the database.
     *
     * @return int the id of the added Achievement
     */
    public function addAchievement(Achievement $achievement): int
    {
        return $this->db->insert('fs_achievement', [
            'name' => $achievement->name,
            'region_id' => $achievement->regionId,
            'description' => $achievement->description,
            'icon' => $achievement->icon,
            'validity_in_days_after_assignment' => $achievement->validityInDaysAfterAssignment,
            'is_requestable_by_foodsaver' => $achievement->isRequestableByFoodsaver,
        ]);
    }

    /**
     * Updates a given achievement in the database.
     * The id of the given Achievement object defines the row to update, the other properties the new values for that achievement.
     */
    public function updateAchievement(Achievement $achievement): void
    {
        $this->db->update('fs_achievement', [
            'name' => $achievement->name,
            'region_id' => $achievement->regionId,
            'description' => $achievement->description,
            'icon' => $achievement->icon,
            'validity_in_days_after_assignment' => $achievement->validityInDaysAfterAssignment,
            'is_requestable_by_foodsaver' => $achievement->isRequestableByFoodsaver,
        ], [
            'id' => $achievement->id
        ]);
    }

    /**
     * Fetches an achievement from the database.
     */
    public function getAchievement(int $id): Achievement
    {
        $achievement = $this->db->fetchById('fs_achievement', '*', $id);

        return Achievement::createFromArray($achievement);
    }

    /**
     * @return array<Achievement> the list of achievements scoped to that region. This does not include those scoped to ancestor regions.
     */
    public function getAchievementsFromRegion(int $regionId): array
    {
        $achievements = $this->db->fetchAllByCriteria('fs_achievement', '*', [
            'region_id' => $regionId,
        ]);

        return array_map([Achievement::class, 'createFromArray'], $achievements);
    }

    public function regionHasAchievements(int $regionId): bool
    {
        return $this->db->exists('fs_achievement', ['region_id' => $regionId]);
    }

    /**
     * Awards an achievement to a user.
     *
     * @return int the id of the added AwardedAchievement (not the achievement id)
     */
    public function awardAchievement(AwardedAchievement $awardedAchievement): int
    {
        return $this->db->insert('fs_foodsaver_has_achievement', [
            'foodsaver_id' => $awardedAchievement->foodsaverId,
            'achievement_id' => $awardedAchievement->achievementId,
            'reviewer_id' => $awardedAchievement->reviewerId,
            'notice' => $awardedAchievement->notice,
            'valid_until' => $awardedAchievement->validUntil,
        ]);
    }

    /**
     * Checks whether a user currently has a certain achievement.
     */
    public function hasAchievement(int $foodsaverId, int $achievementId): bool
    {
        // TODO needs to be adjsted to exclude requests as soon as they can be represented in the database
        return $this->db->exists('fs_foodsaver_has_achievement', [
            'foodsaver_id' => $foodsaverId,
            'achievement_id' => $achievementId,
            'valid_until >' => Carbon::now(),
        ]);
    }
}
