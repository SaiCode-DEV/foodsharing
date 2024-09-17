<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Achievement;

use Foodsharing\Modules\Achievement\DTO\Achievement;
use Foodsharing\Modules\Achievement\DTO\AwardedAchievement;
use Foodsharing\Modules\Achievement\DTO\AwardedAchievementWithAchievementDetails;
use Foodsharing\Modules\Achievement\DTO\AwardedAchievementWithUserDetails;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;

class AchievementGateway extends BaseGateway
{
    public function __construct(
        Database $db,
    ) {
        parent::__construct($db);
    }

    private const AWARDED_ACHIEVEMENT_QUERY = 'SELECT
            user.id AS user_id, user.name as user_name, user.photo AS user_photo,
            reviewer.id AS reviewer_id, reviewer.name AS reviewer_name, reviewer.photo AS reviewer_photo,
            awarded.achievement_id, awarded.notice, awarded.valid_until, awarded.created_at
        FROM fs_foodsaver_has_achievement awarded
        JOIN fs_foodsaver user ON user.id = awarded.foodsaver_id
        LEFT OUTER JOIN fs_foodsaver reviewer ON reviewer.id = awarded.reviewer_id
        WHERE awarded.achievement_id = ? AND (valid_until IS NULL OR valid_until > NOW())';

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

    public function getAchievement(int $achievementId): Achievement
    {
        $achievement = $this->db->fetchById('fs_achievement', '*', $achievementId);

        return new Achievement($achievement);
    }

    /**
     * Fetches all achievements scoped to the given region.
     * @return Achievement[] the list of achievements scoped to that region. This does not include those scoped to ancestor regions.
     */
    public function getAchievementsFromRegion(int $regionId): array
    {
        $achievements = $this->db->fetchAllByCriteria('fs_achievement', '*', [
            'region_id' => $regionId,
        ]);

        return array_map(fn ($data) => new Achievement($data), $achievements);
    }

    /**
     * Checks whether the given region has any achievement.
     */
    public function regionHasAchievements(int $regionId): bool
    {
        return $this->db->exists('fs_achievement', ['region_id' => $regionId]);
    }

    /**
     * Awards an achievement to a user.
     */
    public function awardAchievement(AwardedAchievement $awardedAchievement): void
    {
        $this->db->insert('fs_foodsaver_has_achievement', [
            'foodsaver_id' => $awardedAchievement->foodsaverId,
            'achievement_id' => $awardedAchievement->achievementId,
            'reviewer_id' => $awardedAchievement->reviewerId,
            'notice' => $awardedAchievement->notice,
            'valid_until' => $awardedAchievement->validUntil,
        ]);
    }

    /**
     * Edits an achievement awarded to a user.
     */
    public function editAchievement(AwardedAchievement $awardedAchievement): void
    {
        $this->db->update('fs_foodsaver_has_achievement', [
            'reviewer_id' => $awardedAchievement->reviewerId,
            'notice' => $awardedAchievement->notice,
            'valid_until' => $awardedAchievement->validUntil,
        ], [
            'foodsaver_id' => $awardedAchievement->foodsaverId,
            'achievement_id' => $awardedAchievement->achievementId,
        ]);
    }

    /**
     * Revokes all instances of an achievement from a user.
     */
    public function revokeAchievement(int $foodsaverId, int $achievementId): void
    {
        $this->db->delete('fs_foodsaver_has_achievement', ['foodsaver_id' => $foodsaverId, 'achievement_id' => $achievementId]);
    }

    /**
     * Checks whether a user currently has a certain achievement.
     */
    public function hasAchievement(int $foodsaverId, int $achievementId): bool
    {
        // TODO needs to be adjsted to exclude requests as soon as they can be represented in the database
        try {
            $this->db->fetchValue('SELECT 1
                FROM fs_foodsaver_has_achievement
                WHERE foodsaver_id = ?
                AND achievement_id = ?
                AND (valid_until IS NULL OR valid_until > NOW())',
                [$foodsaverId, $achievementId]);

            return true;
        } catch (DatabaseNoValueFoundException $e) {
            return false;
        }
    }

    /**
     * Retrieves all members with a certain achievement.
     * @return AwardedAchievementWithUserDetails[]
     */
    public function getAwardedUsersForAchievement(int $achievementId): array
    {
        $awardedAchievements = $this->db->fetchAll($this::AWARDED_ACHIEVEMENT_QUERY, [$achievementId]);

        return array_map([AwardedAchievementWithUserDetails::class, 'createFromArray'], $awardedAchievements);
    }

    /**
     * Retrieves a member with a certain achievement.
     */
    public function getAwardedAchievementForUser(int $achievementId, $userId): AwardedAchievementWithUserDetails
    {
        $awardedAchievement = $this->db->fetch($this::AWARDED_ACHIEVEMENT_QUERY . ' AND user.id = ?', [$achievementId, $userId]);

        return AwardedAchievementWithUserDetails::createFromArray($awardedAchievement);
    }

    /**
     * Retrieves all awarded achievements for a certain user.
     * @return AwardedAchievementWithAchievementDetails[]
     */
    public function getAwardedAchievementsForUser(int $userId): array
    {
        $achievements = $this->db->fetchAll('SELECT
                achievement.*,
                awarded.notice, awarded.valid_until, awarded.created_at AS awarded_at
            FROM fs_foodsaver_has_achievement awarded
            JOIN fs_achievement achievement ON achievement.id = awarded.achievement_id
            WHERE awarded.foodsaver_id = ? AND (valid_until IS NULL OR valid_until > NOW())
            ORDER BY created_at DESC', [$userId]);

        return array_map([AwardedAchievementWithAchievementDetails::class, 'createFromArray'], $achievements);
    }
}
