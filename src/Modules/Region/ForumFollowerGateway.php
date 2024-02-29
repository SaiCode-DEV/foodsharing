<?php

namespace Foodsharing\Modules\Region;

use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\DBConstants\Info\FollowStatus;
use Foodsharing\Modules\Core\DBConstants\Info\InfoType;

class ForumFollowerGateway extends BaseGateway
{
    public function getThreadFollower(int $authorId, int $threadId, int $infoType): array
    {
        // Forums save bell infotype in column 'bell_notification'.
        $followerTypeField = [
            InfoType::BELL => 'bell_notification',
            InfoType::EMAIL => 'infotype'
        ][$infoType];

        return $this->db->fetchAll("SELECT
                fs.id,
                fs.name,
                fs.geschlecht,
                fs.email
			FROM fs_foodsaver fs
            JOIN fs_theme_follower tf ON tf.foodsaver_id = fs.id 
			WHERE tf.theme_id = :threadId
			AND tf.foodsaver_id != :authorId
			AND fs.deleted_at IS NULL
			AND tf.{$followerTypeField} = 1",
            [
                ':threadId' => $threadId,
                ':authorId' => $authorId,
            ]);
    }

    public function getEmailSubscribedThreadsForUser(int $fsId): array
    {
        return $this->db->fetchAll('
        SELECT
            th.id,
            th.name AS theme_name,
            tf.infotype,
            b.name AS region_or_group_name

        FROM
            `fs_theme_follower` tf
            LEFT JOIN `fs_theme` th ON tf.theme_id = th.id
            LEFT JOIN `fs_bezirk_has_theme` bht ON tf.theme_id = bht.theme_id
            LEFT JOIN `fs_bezirk` b ON bht.bezirk_id = b.id

        WHERE
            tf.foodsaver_id = :fsId
            AND tf.infotype = 1
    ', [':fsId' => $fsId]);
    }

    public function isFollowingEmail(?int $fsId, int $threadId): bool
    {
        return $this->db->exists(
            'fs_theme_follower',
            ['theme_id' => $threadId, 'foodsaver_id' => $fsId, 'infotype' => InfoType::EMAIL]
        );
    }

    public function isFollowingBell(?int $fsId, int $threadId): bool
    {
        return $this->db->exists(
            'fs_theme_follower',
            ['theme_id' => $threadId, 'foodsaver_id' => $fsId, 'bell_notification' => FollowStatus::ENABLED]
        );
    }

    public function followThreadByEmail(?int $fsId, int $threadId): int
    {
        return $this->db->insertOrUpdate(
            'fs_theme_follower',
            ['foodsaver_id' => $fsId, 'theme_id' => $threadId, 'infotype' => InfoType::EMAIL]
        );
    }

    public function updateInfoType(int $fsId, int $threadId, int $infoType): int
    {
        return $this->db->update(
            'fs_theme_follower',
            ['infotype' => $infoType],
            [
                'foodsaver_id' => $fsId,
                'theme_id' => $threadId,
            ]
        );
    }

    public function followThreadByBell(?int $fsId, int $threadId): int
    {
        return $this->db->insertOrUpdate(
            'fs_theme_follower',
            ['foodsaver_id' => $fsId, 'theme_id' => $threadId, 'bell_notification' => FollowStatus::ENABLED]
        );
    }

    public function unfollowThreadByEmail(?int $fsId, int $threadId): int
    {
        return $this->db->insertOrUpdate(
            'fs_theme_follower',
            ['foodsaver_id' => $fsId, 'theme_id' => $threadId, 'infotype' => InfoType::NONE]
        );
    }

    public function unfollowThreadByBell(?int $fsId, int $threadId): int
    {
        return $this->db->insertOrUpdate(
            'fs_theme_follower',
            ['foodsaver_id' => $fsId, 'theme_id' => $threadId, 'bell_notification' => FollowStatus::DISABLED]
        );
    }

    /**
     * Removes the forum subscription for one foodsaver from the region or group.
     *
     * @param int $regionId id of the group
     * @param int $foodsaverId id of the foodsaver
     *
     * @throws \Exception
     */
    public function deleteForumSubscription(int $regionId, int $foodsaverId): void
    {
        $themeIds = $this->db->fetchAllValuesByCriteria('fs_bezirk_has_theme', 'theme_id', ['bezirk_id' => $regionId]);
        $this->db->delete('fs_theme_follower', ['theme_id' => $themeIds, 'foodsaver_id' => $foodsaverId]);
    }

    /**
     * Removes the forum subscriptions for all deleted members or ambassadors in the region or group.
     *
     * @param int $regionId id of the group
     * @param array $remainingMemberIds list of remaining members, or null to remove all
     * @param bool $useAmbassadors if the ambassador table should be used
     */
    public function deleteForumSubscriptions(int $regionId, array $remainingMemberIds, bool $useAmbassadors): void
    {
        $foodsaverTableName = $useAmbassadors ? 'fs_botschafter' : 'fs_foodsaver_has_bezirk';
        $threadIds = $this->db->fetchAllValuesByCriteria('fs_bezirk_has_theme', 'theme_id', ['bezirk_id' => $regionId]);

        if (!empty($threadIds)) {
            $query = '
				DELETE	tf.*
				FROM		`fs_theme_follower` tf
				JOIN		`fs_bezirk_has_theme` ht
				ON			ht.`theme_id` = tf.`theme_id`
				LEFT JOIN	`' . $foodsaverTableName . '` b
				ON			b.`bezirk_id` = ht.`bezirk_id`
				AND			b.`foodsaver_id` = tf.`foodsaver_id`
				WHERE		tf.`theme_id` IN (' . implode(',', array_map('intval', $threadIds)) . ')
			';
            if (!empty($remainingMemberIds)) {
                $query .= 'AND	tf.`foodsaver_id` NOT IN(' . implode(',', array_map('intval', $remainingMemberIds)) . ')';
            }

            $this->db->execute($query);
        }
    }

    /**
     * Recreates the default behaviour of pre may 2020 release by adding bell notifications for everybody who did not set/disable
     * email notifications for a certain thread.
     *
     * @return int number of inserted entries
     */
    public function createFollowerEntriesForExistingThreads(): int
    {
        $query = 'INSERT IGNORE INTO fs_theme_follower (foodsaver_id, theme_id, infotype, bell_notification)
				SELECT foodsaver_id, theme_id, 0, 1 FROM fs_theme_post';

        return $this->db->execute($query)->rowCount();
    }
}
