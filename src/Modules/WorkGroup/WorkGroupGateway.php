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

    /*
     * Own existing applications.
     */
    public function getApplications(int $fsId): array
    {
        $ret = $this->db->fetchAllValues('
			SELECT	`bezirk_id`
			FROM	`fs_foodsaver_has_bezirk`
			WHERE	`active` != :active
			AND		`foodsaver_id` = :foodsaver_id
		', [':active' => 1, ':foodsaver_id' => $fsId]);
        if ($ret) {
            $out = [];
            foreach ($ret as $gid) {
                $out[$gid] = $gid;
            }

            return $out;
        }

        return [];
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
					b.`banana_count`,
					b.`week_num`,
					b.`fetch_count`,
					b.`type`,
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

    public function listGroups(int $parentId): array
    {
        $groups = $this->db->fetchAll('
			SELECT		b.`id`,
						b.`name`,
						b.`parent_id`,
						b.`teaser`,
						b.`photo`,
						b.`apply_type`,
						b.`banana_count`,
						b.`week_num`,
						b.`fetch_count`,
						CONCAT(m.`name`,"@' . PLATFORM_MAILBOX_HOST . '") AS email
			FROM		`fs_bezirk` b
			LEFT JOIN	`fs_mailbox` m
			ON			b.`mailbox_id` = m.`id`
			WHERE		b.`parent_id` = :parent_id
			AND			b.`type` = :bezirk_type
			ORDER BY	`name`
		', [':parent_id' => $parentId, ':bezirk_type' => UnitType::WORKING_GROUP]);
        if ($groups) {
            foreach ($groups as $i => $g) {
                $members = $this->db->fetchAll('
					SELECT		`id`,
								`name`,
								`photo`
					FROM		`fs_foodsaver` fs
					INNER JOIN	`fs_foodsaver_has_bezirk` hb
					ON			hb.`foodsaver_id` = fs.id
					WHERE		hb.`bezirk_id` = :bezirk_id
					AND			hb.`active` = 1
				', [':bezirk_id' => $g['id']]);
                $leaders = $this->db->fetchAll('
					SELECT		`id`,
								`name`,
								`photo`
					FROM		`fs_foodsaver` fs
					INNER JOIN	`fs_botschafter` hb
					ON			hb.`foodsaver_id` = fs.id
					WHERE		hb.`bezirk_id` = :bezirk_id
				', [':bezirk_id' => $g['id']]);
                $groups[$i]['members'] = $members ?: [];
                $groups[$i]['leaders'] = $leaders ?: [];
                try {
                    $groups[$i]['function'] = $this->db->fetchValueByCriteria('fs_region_function', 'function_id',
                        ['region_id' => $g['id']]
                    );
                } catch (Exception) {
                    $groups[$i]['function'] = null;
                }
            }

            return $groups;
        }

        return [];
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

    public function getGroupMail(int $regionId): string
    {
        return $this->db->fetchValue('
			SELECT		CONCAT(mb.`name`,"@' . PLATFORM_MAILBOX_HOST . '")
			FROM		`fs_bezirk` bz
			INNER JOIN	`fs_mailbox` mb
			ON			bz.`mailbox_id` = mb.`id`
			WHERE		bz.`id` = :bezirk_id
		', [':bezirk_id' => $regionId]);
    }

    public function updateGroup(int $regionId, EditWorkGroupData $group): int
    {
        $description = $group->description == null ? null : strip_tags($group->description);
        $photo = $group->photo == null ? '' : strip_tags($group->photo);

        return $this->db->update(
            'fs_bezirk',
            [
                'name' => strip_tags($group->name),
                'teaser' => $description,
                'photo' => $photo,
                'apply_type' => $group->applyType,
                'banana_count' => $group->requiredBananas,
                'fetch_count' => $group->requiredPickups,
                'week_num' => $group->requiredWeeks
            ],
            ['id' => $regionId]
        );
    }

    public function getStats(int $fsId): array
    {
        $ret = $this->db->fetchByCriteria(
            'fs_foodsaver',
            ['anmeldedatum', 'stat_fetchcount', 'stat_bananacount'],
            ['id' => $fsId]
        );
        if ($ret) {
            $time = strtotime($ret['anmeldedatum']);
            // 604800 = seconds per week
            $weeks = (int)round((time() - $time) / 604800);

            return [
                'weeks' => $weeks,
                'fetchcount' => $ret['stat_fetchcount'],
                'bananacount' => $ret['stat_bananacount'],
            ];
        }

        return [];
    }

    public function getCountryGroups(): array
    {
        return $this->db->fetchAll('
			SELECT	`id`,
					`name`,
					`parent_id`
			FROM	`fs_bezirk`
			WHERE	`type` = :type
		', [':type' => UnitType::COUNTRY]);
    }
}
