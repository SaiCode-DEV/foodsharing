<?php

namespace Foodsharing\Modules\WorkGroup;

use Exception;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\RestApi\Models\Group\EditWorkGroupData;

class WorkGroupGateway extends BaseGateway
{
    public function __construct(
        Database $db
    ) {
        parent::__construct($db);
    }

    public function getGroup(int $regionId): array
    {
        $group = $this->db->fetch('
			SELECT	b.`id`,
					b.`name`,
					b.`parent_id`,
					b.`teaser`,
					b.`photo`,
					b.`email_name`,
					b.`apply_type`,
                    b.`application_prompt`,
					b.`type`,
                    b.`category_id`,
					CONCAT(m.`name`,"@' . PLATFORM_MAILBOX_HOST . '") AS email
			FROM		`fs_bezirk` b
			LEFT JOIN	`fs_mailbox` m
			ON			b.`mailbox_id` = m.`id`
			WHERE	b.`id` = :bezirk_id
		', [':bezirk_id' => $regionId]);
        if ($group) {
            $group['member'] = $this->db->fetchAll('
				SELECT		`id`,
							`name`,
							`photo`
				FROM		`fs_foodsaver` fs
				INNER JOIN	`fs_foodsaver_has_bezirk` hb
				ON			hb.`foodsaver_id` = fs.`id`
				WHERE		hb.`bezirk_id` = :bezirk_id
				AND			hb.`active` = 1
			', [':bezirk_id' => $regionId]);
            $group['leader'] = $this->db->fetchAll('
				SELECT		`id`,
							`name`,
							`photo`
				FROM		`fs_foodsaver` fs
				INNER JOIN	`fs_botschafter` hb
				ON			hb.`foodsaver_id` = fs.`id`
				WHERE		hb.`bezirk_id` = :bezirk_id
			', [':bezirk_id' => $regionId]);
        }

        return $group;
    }

    public function addToGroup(int $regionId, int $fsId): int
    {
        return $this->db->insertOrUpdate(
            'fs_foodsaver_has_bezirk',
            [
                'foodsaver_id' => $fsId,
                'bezirk_id' => $regionId,
                'active' => 1,
                'added' => $this->db->now()
            ]
        );
    }

    /**
     * Removes an active member from the group.
     *
     * @throws Exception
     */
    public function removeFromGroup(int $groupId, int $fsId): void
    {
        $this->db->delete(
            'fs_foodsaver_has_bezirk',
            [
                'bezirk_id' => $groupId,
                'foodsaver_id' => $fsId,
                'active' => 1
            ]
        );
    }

    public function listMemberGroups(int $fsId): array
    {
        return $this->db->fetchAll('
			SELECT		b.`id`,
						b.`name`,
						b.`teaser`,
						b.`photo`,
						hb.`notify_by_email_about_new_threads` as notifyByEmailAboutNewThreads

			FROM		`fs_bezirk` b
			INNER JOIN	`fs_foodsaver_has_bezirk` hb
			ON			hb.`bezirk_id` = b.`id`
			WHERE		hb.`foodsaver_id` = :foodsaver_id
			AND			b.`type` = :bezirk_type
			AND			hb.active = :active
			ORDER BY	b.`name`
		', [':foodsaver_id' => $fsId, ':bezirk_type' => UnitType::WORKING_GROUP, ':active' => 1]);
    }

    public function groupApply(int $regionId, int $fsId, string $application): int
    {
        return $this->db->insertOrUpdate(
            'fs_foodsaver_has_bezirk',
            [
                'foodsaver_id' => $fsId,
                'bezirk_id' => $regionId,
                'active' => 0,
                'added' => $this->db->now(),
                'application' => strip_tags($application)
            ]
        );
    }

    public function hasApplied(int $regionId, int $fsId): bool
    {
        return $this->db->exists('fs_foodsaver_has_bezirk', [
            'foodsaver_id' => $fsId,
            'bezirk_id' => $regionId,
            'active' => 0
        ]);
    }

    public function getFsWithMail(int $fsId): array
    {
        return $this->db->fetch('
			SELECT		fs.`id`,
						fs.`name`,
						IF(mb.`name` IS NULL, fs.`email`, CONCAT(mb.`name`,"@' . PLATFORM_MAILBOX_HOST . '")) AS email
			FROM		`fs_foodsaver` fs
			LEFT JOIN	`fs_mailbox` mb
			ON			fs.`mailbox_id` = mb.`id`
			WHERE		fs.`id` = :fs_id
		', [':fs_id' => $fsId]);
    }

    public function updateGroup(int $regionId, EditWorkGroupData $group): int
    {
        $description = $group->description == null ? null : strip_tags($group->description);
        $photo = is_null($group->photo) ? '' : strip_tags($group->photo);

        return $this->db->update(
            'fs_bezirk',
            [
                'name' => strip_tags($group->name),
                'teaser' => $description,
                'photo' => $photo,
                'apply_type' => $group->applyType,
                'application_prompt' => strip_tags($group->applicationPrompt),
                'category_id' => $group->groupCategory,
            ],
            ['id' => $regionId]
        );
    }

    /**
     * Returns the ids of all admins of the specified working group.
     *
     * @return int[]
     */
    public function getGroupAdminIds(int $groupId): array
    {
        return $this->db->fetchAllValuesByCriteria('fs_botschafter', 'foodsaver_id',
            ['bezirk_id' => $groupId]
        );
    }

    /**
     * Checks if the specified region is a working group.
     *
     * @param int $regionId the ID of the region to check
     * @return bool true if the region is a working group, false otherwise
     */
    public function regionIsWorkingGroup(int $regionId): bool
    {
        return $this->db->fetchValue('SELECT COUNT(*) FROM fs_bezirk WHERE id = :id AND type = :type', [
            'id' => $regionId,
            'type' => UnitType::WORKING_GROUP
        ]) > 0;
    }

    public function fetchDataForGroupList(int $regionId, int $userId): array
    {
        $groups = $this->db->fetchAll('SELECT
                g.id, g.name, g.apply_type, g.application_prompt, g.photo, g.teaser, g.category_id,
                mail.name AS email,
                (SELECT COUNT(*) FROM `fs_foodsaver_has_bezirk` member WHERE member.bezirk_id = g.id AND member.active = 1) AS memberCount,
                this_member.active, -- may be unnecessary, depending on whether the session includes applications
                (SELECT function_id FROM `fs_region_function` WHERE region_id = g.id LIMIT 1) AS function_id,
                (
                    SELECT MAX(la_post.time)
                    FROM fs_bezirk_has_theme ht
                    JOIN fs_theme t ON t.id = ht.theme_id
                    JOIN fs_theme_post la_post ON la_post.id = t.last_post_id
                    WHERE ht.bezirk_id = g.id
                ) AS latest_activity
            FROM `fs_bezirk` g
            INNER JOIN `fs_mailbox` mail ON g.`mailbox_id` = mail.`id`
            LEFT OUTER JOIN `fs_foodsaver_has_bezirk` this_member ON g.id = this_member.bezirk_id AND this_member.foodsaver_id = :userId
            WHERE g.`parent_id` = :parentId AND g.`type` = :groupType', [
            'userId' => $userId,
            'parentId' => $regionId,
            'groupType' => UnitType::WORKING_GROUP
        ]);

        $subGroups = $this->db->fetchAll('SELECT
                g.id AS groupId,
                sub.id, sub.name,
                mail.name AS email,
                (
                    SELECT MAX(la_post.time)
                    FROM fs_bezirk_has_theme ht
                    JOIN fs_theme t ON t.id = ht.theme_id
                    JOIN fs_theme_post la_post ON la_post.id = t.last_post_id
                    WHERE ht.bezirk_id = sub.id
                ) AS latest_activity
            FROM `fs_bezirk` g
            INNER JOIN `fs_bezirk` sub ON sub.`parent_id` = g.`id`
            INNER JOIN `fs_mailbox` mail ON sub.mailbox_id = mail.id
            WHERE g.`parent_id` = :parentId AND g.`type` = :groupType', [
            'parentId' => $regionId,
            'groupType' => UnitType::WORKING_GROUP
        ]);

        $admins = $this->db->fetchAll('SELECT
                g.id AS groupId,
                fs.id, fs.name, fs.photo, fs.is_sleeping
            FROM `fs_bezirk` g
            INNER JOIN fs_botschafter `admin` ON g.id = `admin`.bezirk_id
            INNER JOIN fs_foodsaver fs ON `admin`.foodsaver_id = fs.id AND fs.deleted_at IS NULL
            WHERE g.`parent_id` = :parentId AND g.`type` = :groupType', [
            'parentId' => $regionId,
            'groupType' => UnitType::WORKING_GROUP
        ]);

        return [
            'groups' => $groups,
            'subGroups' => $subGroups,
            'admins' => $admins
        ];
    }
}
