<?php

namespace Foodsharing\Modules\Region;

use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;
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
    public function deleteForumSubscription(int $regionId, int $foodsaverId, ?int $subforum = null): void
    {
        $criteria = ['bezirk_id' => $regionId];
        if (!is_null($subforum)) {
            $criteria['bot_theme'] = $subforum;
        }
        $themeIds = $this->db->fetchAllValuesByCriteria('fs_bezirk_has_theme', 'theme_id', $criteria);
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

    /**
     * Returns the ids of all admins of a group / ambassadors of a region who didn't disable bells for new threads
     * By default (null value) admins are included. Only explicitly not recieving these bells excludes them.
     * @return int[]
     */
    public function getAdminIdsToNotifyForNewThread(int $regionId): array
    {
        return $this->db->fetchAllValues('SELECT fhb.foodsaver_id
            FROM fs_foodsaver_has_bezirk fhb
            JOIN fs_botschafter b ON b.bezirk_id = fhb.bezirk_id AND b.foodsaver_id = fhb.foodsaver_id
            WHERE b.bezirk_id = ?
            AND (notify_on_all_new_threads IS NULL OR notify_on_all_new_threads != 0)',
            [$regionId]);
    }

    /**
     * Returns the ids of all group / region members who did enable bells for new threads
     * By default (null value) users are excluded. Only explicitly recieving these bells includes them.
     * @return int[]
     */
    public function getUserIdsToNotifyForNewThread(int $regionId): array
    {
        return $this->db->fetchAllValuesByCriteria('fs_foodsaver_has_bezirk', 'foodsaver_id', [
            'bezirk_id' => $regionId,
            'notify_on_all_new_threads' => 1,
            'active' => 1,
        ]);
    }

    public function isFollowingForum(int $regionId, int $foodsaverId): ?bool
    {
        try {
            $isFollowing = $this->db->fetchValueByCriteria('fs_foodsaver_has_bezirk', 'notify_on_all_new_threads', [
                'bezirk_id' => $regionId,
                'foodsaver_id' => $foodsaverId,
            ]);

            return is_null($isFollowing) ? null : boolval($isFollowing);
        } catch (DatabaseNoValueFoundException $e) {
            return null;
        }
    }

    public function setFollowingForum(int $regionId, int $foodsaverId, bool $isFollowing): void
    {
        $this->db->insertOrUpdate('fs_foodsaver_has_bezirk', [
            'bezirk_id' => $regionId,
            'foodsaver_id' => $foodsaverId,
            'notify_on_all_new_threads' => intval($isFollowing),
        ]);
    }
}
