<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Achievement;

use DateTime;
use Foodsharing\Modules\Achievement\DTO\Achievement;
use Foodsharing\Modules\Achievement\DTO\AwardedAchievement;
use Foodsharing\Modules\Achievement\DTO\AwardedAchievementWithAchievementDetails;
use Foodsharing\Modules\Achievement\DTO\AwardedAchievementWithUserDetails;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;
use Foodsharing\Modules\Core\DBConstants\Achievement\DuplicateMode;
use Foodsharing\Modules\Core\DBConstants\Achievement\VisibilityType;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\Store\DTO\CommonLabel;

class AchievementGateway extends BaseGateway
{
    public function __construct(
        Database $db,
    ) {
        parent::__construct($db);
    }

    private const string AWARDED_ACHIEVEMENT_QUERY = 'SELECT
            user.id AS user_id, user.name as user_name, user.photo AS user_photo, user.is_sleeping AS user_is_sleeping,
            reviewer.id AS reviewer_id, reviewer.name AS reviewer_name, reviewer.photo AS reviewer_photo, reviewer.is_sleeping AS reviewer_is_sleeping,
            awarded.id, awarded.achievement_id, awarded.notice, awarded.valid_until, awarded.created_at
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
            'visibility_type' => $achievement->visibilityType->value,
            'duplicate_mode' => $achievement->duplicateMode->value,
        ]);
    }

    /**
     * Updates a given achievement in the database.
     * The id of the given Achievement object defines the row to update, the other properties the new values for that achievement.
     */
    public function updateAchievement(Achievement $achievement): bool
    {
        return $this->db->update('fs_achievement', [
            'name' => $achievement->name,
            'region_id' => $achievement->regionId,
            'description' => $achievement->description,
            'icon' => $achievement->icon,
            'validity_in_days_after_assignment' => $achievement->validityInDaysAfterAssignment,
            'visibility_type' => $achievement->visibilityType->value,
            'duplicate_mode' => $achievement->duplicateMode->value,
        ], [
            'id' => $achievement->id
        ]) > 0;
    }

    /**
     * Deletes a given achievement in the database.
     */
    public function deleteAchievement(int $achievementId): bool
    {
        return $this->db->delete('fs_achievement', ['id' => $achievementId]) > 0;
    }

    public function getAchievement(int $achievementId): Achievement
    {
        $achievement = $this->db->fetchById('fs_achievement', '*', $achievementId);

        return Achievement::create(
            $achievement['id'],
            $achievement['region_id'],
            $achievement['name'],
            $achievement['description'],
            $achievement['icon'],
            $achievement['validity_in_days_after_assignment'],
            new DateTime($achievement['created_at']),
            isset($achievement['updated_at']) ? new DateTime($achievement['updated_at']) : null,
            VisibilityType::from($achievement['visibility_type']),
            DuplicateMode::from($achievement['duplicate_mode']),
        );
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

        return array_map(function ($achievementData) {
            return Achievement::create(
                $achievementData['id'],
                $achievementData['region_id'],
                $achievementData['name'],
                $achievementData['description'],
                $achievementData['icon'],
                $achievementData['validity_in_days_after_assignment'],
                new DateTime($achievementData['created_at']),
                isset($achievementData['updated_at']) ? new DateTime($achievementData['updated_at']) : null,
                VisibilityType::from($achievementData['visibility_type']),
                DuplicateMode::from($achievementData['duplicate_mode']),
            );
        }, $achievements);
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
     * @return int the id of the awarded achievement entry
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
     * Edits an achievement awarded to a user.
     */
    public function editAwardedAchievement(AwardedAchievement $awardedAchievement): void
    {
        $this->db->update('fs_foodsaver_has_achievement', [
            'reviewer_id' => $awardedAchievement->reviewerId,
            'notice' => $awardedAchievement->notice,
            'valid_until' => $awardedAchievement->validUntil,
        ], [
            'id' => $awardedAchievement->id,
        ]);
    }

    /**
     * Revokes the given instance of an achievement from a user.
     */
    public function revokeAchievement(int $awardedAchievementId): void
    {
        $this->db->delete('fs_foodsaver_has_achievement', ['id' => $awardedAchievementId]);
    }

    /**
     * Checks whether a user currently has a certain achievement.
     * @param DateTime $time The time at which the achievement should be valid, defaults to now
     */
    public function hasAchievement(int $foodsaverId, int $achievementId, ?DateTime $time = null): bool
    {
        $time ??= new DateTime('now');

        try {
            $this->db->fetchValue('SELECT 1
                FROM fs_foodsaver_has_achievement
                WHERE foodsaver_id = ?
                AND achievement_id = ?
                AND (valid_until IS NULL OR valid_until > ?)',
                [$foodsaverId, $achievementId, $time->format('Y-m-d H:i:s')]);

            return true;
        } catch (DatabaseNoValueFoundException) {
            return false;
        }
    }

    /**
     * Retrieves the latest current awarded achievement id for a certain user and achievement.
     */
    public function getCurrentAwardedAchievementId(int $foodsaverId, int $achievementId): int
    {
        return (int)$this->db->fetchValue('SELECT id
            FROM fs_foodsaver_has_achievement
            WHERE foodsaver_id = ?
            AND achievement_id = ?
            AND (valid_until IS NULL OR valid_until > NOW())
            ORDER BY created_at DESC
            LIMIT 1',
            [$foodsaverId, $achievementId]);
    }

    /**
     * Retrieves all members with a certain achievement.
     * @return AwardedAchievementWithUserDetails[]
     */
    public function getAwardedUsersForAchievement(int $achievementId): array
    {
        $awardedAchievements = $this->db->fetchAll($this::AWARDED_ACHIEVEMENT_QUERY, [$achievementId]);

        return array_map(function ($awardedAchievement) {
            return $this->convertDataToAwardedAchievementWithUserDetails($awardedAchievement);
        }, $awardedAchievements);
    }

    /**
     * Retrieves a member with a certain achievement.
     */
    public function getAwardedAchievementForUser(int $achievementId, $userId): AwardedAchievementWithUserDetails
    {
        $awardedAchievement = $this->db->fetch($this::AWARDED_ACHIEVEMENT_QUERY . ' AND user.id = ?', [$achievementId, $userId]);

        return $this->convertDataToAwardedAchievementWithUserDetails($awardedAchievement);
    }

    /**
     * Retrieves all awarded achievements for a certain user.
     * @return AwardedAchievementWithAchievementDetails[]
     */
    public function getAwardedAchievementsForUser(int $userId): array
    {
        $achievements = $this->db->fetchAll('SELECT
                achievement.*,
                awarded.notice, awarded.valid_until, awarded.created_at AS awarded_at,
                region.id AS region_id, region.name AS region_name
            FROM fs_foodsaver_has_achievement awarded
            JOIN fs_achievement achievement ON achievement.id = awarded.achievement_id
            JOIN fs_bezirk region ON region.id = achievement.region_id
            WHERE awarded.foodsaver_id = ? AND (valid_until IS NULL OR valid_until > NOW())
            ORDER BY created_at DESC', [$userId]);

        return array_map(function ($achievement) {
            return AwardedAchievementWithAchievementDetails::createWithDetails(
                $achievement['id'],
                $achievement['region_id'],
                $achievement['name'],
                $achievement['description'],
                $achievement['icon'],
                $achievement['validity_in_days_after_assignment'],
                new DateTime($achievement['awarded_at']),
                isset($achievement['updated_at']) ? new DateTime($achievement['updated_at']) : null,
                $achievement['notice'],
                isset($achievement['valid_until']) ? new DateTime($achievement['valid_until']) : null,
                VisibilityType::from($achievement['visibility_type']),
                DuplicateMode::from($achievement['duplicate_mode']),
                new CommonLabel($achievement['region_id'], $achievement['region_name']),
            );
        }, $achievements);
    }

    /**
     * Creates object from raw sql data.
     * @param array $awardedAchievement associative array with db column title as key
     */
    private function convertDataToAwardedAchievementWithUserDetails(array $awardedAchievement): AwardedAchievementWithUserDetails
    {
        $achievementId = $awardedAchievement['achievement_id'];
        $user = new Profile($awardedAchievement, 'user_');
        $reviewer = $awardedAchievement['reviewer_id'] ? new Profile($awardedAchievement, 'reviewer_') : null;
        $notice = $awardedAchievement['notice'];
        $validUntil = isset($awardedAchievement['valid_until']) ? new DateTime($awardedAchievement['valid_until']) : null;
        $createdAt = new DateTime($awardedAchievement['created_at']);

        return AwardedAchievementWithUserDetails::create($achievementId, $user, $reviewer, $achievementId, $notice, $validUntil, $createdAt);
    }

    public function getAwardedAchievementById(int $awardedAchievementId): AwardedAchievement
    {
        $awardedAchievement = $this->db->fetchByCriteria('fs_foodsaver_has_achievement', '*', ['id' => $awardedAchievementId]);

        return AwardedAchievement::create(
            $awardedAchievement['id'],
            $awardedAchievement['foodsaver_id'],
            $awardedAchievement['achievement_id'],
            $awardedAchievement['reviewer_id'],
            $awardedAchievement['notice'],
            !is_null($awardedAchievement['valid_until']) ? new DateTime($awardedAchievement['valid_until']) : null,
            new DateTime($awardedAchievement['created_at']),
            !is_null($awardedAchievement['updated_at']) ? new DateTime($awardedAchievement['updated_at']) : null
        );
    }

    /**
     * Retrieves a member with a certain achievement.
     */
    public function getAwardedAchievementWithUserDetails(int $achievementId, int $awardedAchievementId): AwardedAchievementWithUserDetails
    {
        $awardedAchievement = $this->db->fetch($this::AWARDED_ACHIEVEMENT_QUERY . ' AND awarded.id = ?', [$achievementId, $awardedAchievementId]);

        return AwardedAchievementWithUserDetails::create(
            $awardedAchievement['id'],
            new Profile($awardedAchievement, 'user_'),
            $awardedAchievement['reviewer_id'] ? new Profile($awardedAchievement, 'reviewer_') : null,
            $awardedAchievement['achievement_id'],
            $awardedAchievement['notice'],
            isset($awardedAchievement['valid_until']) ? new DateTime($awardedAchievement['valid_until']) : null,
            new DateTime($awardedAchievement['created_at']),
        );
    }
}
