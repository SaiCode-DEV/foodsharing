<?php

namespace Foodsharing\Modules\Region;

use Exception;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Region\RegionOptionType;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\Group\GroupFunctionGateway;

class RegionGateway extends BaseGateway
{
    private FoodsaverGateway $foodsaverGateway;
    private GroupFunctionGateway $groupFunctionGateway;

    public function __construct(
        Database $db,
        FoodsaverGateway $foodsaverGateway,
        GroupFunctionGateway $groupFunctionGateway
    ) {
        parent::__construct($db);
        $this->foodsaverGateway = $foodsaverGateway;
        $this->groupFunctionGateway = $groupFunctionGateway;
    }

    public function getRegion(int $regionId): ?array
    {
        if ($regionId == RegionIDs::ROOT) {
            return null;
        }

        return $this->db->fetchByCriteria('fs_bezirk',
            ['name', 'id', 'email', 'email_name', 'has_children', 'parent_id', 'mailbox_id', 'type'],
            ['id' => $regionId]
        );
    }

    public function listRegionsIncludingParents(array $regionId): array
    {
        $stm = 'SELECT DISTINCT ancestor_id FROM `fs_bezirk_closure` WHERE bezirk_id IN (' . implode(',', array_map('intval', $regionId)) . ')';

        return $this->db->fetchAllValues($stm);
    }

    public function getBasics_bezirk(): array
    {
        return $this->db->fetchAll('
			SELECT 	 	`id`,
						`name`

			FROM 		`fs_bezirk`
			ORDER BY `name`');
    }

    public function getBezirkByParent(int $parentId, bool $includeOrga = false): array
    {
        $sql = 'AND 		`type` != ' . UnitType::WORKING_GROUP;
        if ($includeOrga) {
            $sql = '';
        }

        return $this->db->fetchAll('
			SELECT
				`id`,
				`name`,
				`has_children`,
				`parent_id`,
				`type`,
				`master`
			FROM 		`fs_bezirk`
			WHERE 		`parent_id` = :id
			AND id != :rootId
			' . $sql . '
			ORDER BY 	`name`',
            [
                ':rootId' => RegionIDs::ROOT,
                ':id' => $parentId
            ]
        );
    }

    public function listIdsForFoodsaverWithDescendants(?int $foodsaverId): array
    {
        if ($foodsaverId === null) {
            return [];
        }
        $bezirk_ids = [];
        foreach ($this->listForFoodsaver($foodsaverId) as $bezirk) {
            $bezirk_ids += $this->listIdsForDescendantsAndSelf($bezirk['id']);
        }

        return $bezirk_ids;
    }

    /**
     * @return bool true when the given user is active (an accepted member) in the given region
     */
    public function hasMember(int $foodsaverId, int $regionId): bool
    {
        return $this->db->exists('fs_foodsaver_has_bezirk', ['bezirk_id' => $regionId, 'foodsaver_id' => $foodsaverId, 'active' => 1]);
    }

    /**
     * @return bool true when the given user is an admin/ambassador for the given group/region
     */
    public function isAdmin(int $foodsaverId, int $regionId): bool
    {
        return $this->db->exists('fs_botschafter', ['bezirk_id' => $regionId, 'foodsaver_id' => $foodsaverId]);
    }

    /**
     * @return bool true when the given user is an admin/ambassador for the given group/region
     */
    public function isAmbassadorOfAtLeastOneRegion(int $foodsaverId): bool
    {
        return $this->db->fetchValue('SELECT COUNT(*)
            FROM `fs_botschafter` ambassador
            JOIN fs_bezirk region on region.id = ambassador.bezirk_id
            WHERE region.type != :working_group_type
            AND ambassador.foodsaver_id = :foodsaver_id',
            [
                'working_group_type' => UnitType::WORKING_GROUP,
                'foodsaver_id' => $foodsaverId,
            ]
        ) > 0;
    }

    public function listForFoodsaver(?int $foodsaverId): array
    {
        if ($foodsaverId === null) {
            return [];
        }
        $values = $this->db->fetchAll(
            '
			SELECT 	b.`id`,
					b.name,
					b.type,
					b.parent_id

			FROM 	`fs_foodsaver_has_bezirk` hb,
					`fs_bezirk` b

			WHERE 	hb.bezirk_id = b.id
			AND 	`foodsaver_id` = :fs_id
			AND 	hb.active = 1

			ORDER BY b.name',
            [':fs_id' => $foodsaverId]
        );

        $output = [];
        foreach ($values as $v) {
            $output[$v['id']] = $v;
        }

        return $output;
    }

    public function getFsRegionIds(int $foodsaverId): array
    {
        return $this->db->fetchAllValuesByCriteria('fs_foodsaver_has_bezirk', 'bezirk_id',
            ['foodsaver_id' => $foodsaverId]
        );
    }

    public function listIdsForDescendantsAndSelf(int $regionId, bool $includeSelf = true, bool $includeWorkgroups = true): array
    {
        if ($regionId == RegionIDs::ROOT) {
            return [];
        }
        if ($includeSelf) {
            $minDepth = 0;
        } else {
            $minDepth = 1;
        }

        if ($includeWorkgroups) {
            return $this->db->fetchAllValuesByCriteria('fs_bezirk_closure', 'bezirk_id',
                ['ancestor_id' => $regionId, 'depth >=' => $minDepth]
            );
        } else {
            return $this->db->fetchAllValues(
                'SELECT
						fbc.bezirk_id
					FROM `fs_bezirk_closure` fbc
					left outer join `fs_bezirk` reg on fbc.bezirk_id = reg.id
					  WHERE
						fbc.ancestor_id = :regionId
					AND fbc.depth >= :min_depth
					and reg.type <> :regionTypeWorkGroup',
                ['regionId' => $regionId, 'min_depth' => $minDepth, 'regionTypeWorkGroup' => UnitType::WORKING_GROUP]
            );
        }
    }

    public function listForFoodsaverExceptWorkingGroups(int $foodsaverId, bool $excludeWorkingGroups = true): array
    {
        $operator = $excludeWorkingGroups ? '!=' : '=';

        $regions = $this->db->fetchAll('
        SELECT
            b.`id`,
            b.`name`,
            b.`teaser`,
            b.`photo`,
            hb.`notify_by_email_about_new_threads` as notifyByEmailAboutNewThreads

        FROM
            fs_bezirk b,
            fs_foodsaver_has_bezirk hb

        WHERE
            hb.bezirk_id = b.id
        AND
            hb.`active` = 1

        AND
            hb.`foodsaver_id` = :foodsaverId

        AND
            b.`type` ' . $operator . ' :workGroupType

        ORDER BY
            b.`name`
    ', [
            ':foodsaverId' => $foodsaverId,
            ':workGroupType' => UnitType::WORKING_GROUP
        ]);

        return $regions;
    }

    /**
     * Fetches details for a region.
     *
     * Warning: this function does not properly set the moderated flag for large regions. In most cases you might want
     * to use RegionTransactions::getRegionDetails instead.
     */
    public function getRegionDetails(int $regionId): array
    {
        $region = $this->db->fetch('
			SELECT
				b.`id`,
			    b.parent_id,
				b.`name`,
				b.`email`,
				b.`email_name`,
				b.`mailbox_id`,
				b.`type`,
				b.`stat_fetchweight`,
				b.`stat_fetchcount`,
				b.`stat_fscount`,
				b.`stat_botcount`,
				b.`stat_postcount`,
				b.`stat_betriebcount`,
				b.`stat_korpcount`,
				b.`moderated`,
				b.`has_children`,
				(
					SELECT 	count(c.`foodsaver_id`)
					FROM 	`fs_foodsaver_has_bezirk` c
					LEFT JOIN `fs_foodsaver` fs ON c.`foodsaver_id` = fs.id
					WHERE     fs.deleted_at IS NULL
					AND 	c.bezirk_id = b.id
					AND 	c.active = 1
					AND 	fs.sleep_status = 0
				) AS fs_count,
				(
					SELECT 	count(fs.`id`)
					FROM 	`fs_foodsaver` fs
					WHERE	fs.deleted_at IS NULL
					AND 	fs.bezirk_id = b.id
					AND 	fs.sleep_status = 0
				) AS fs_home_count,
				(
					SELECT 	count(c.`foodsaver_id`)
					FROM 	`fs_foodsaver_has_bezirk` c
					LEFT JOIN `fs_foodsaver` fs ON c.`foodsaver_id` = fs.id
					WHERE     fs.deleted_at IS NULL
					AND 	c.bezirk_id = b.id
					AND 	c.active = 1
					AND 	fs.sleep_status > 0
				) AS sleeper_count

			FROM 	`fs_bezirk` AS b

			WHERE 	b.`id` = :id
			LIMIT 1
		', ['id' => $regionId]);

        $region['botschafter'] = $this->foodsaverGateway->getAdminsOrAmbassadors($regionId);
        shuffle($region['botschafter']);

        $functionMappings = [
            WorkgroupFunction::WELCOME => 'welcomeAdmins',
            WorkgroupFunction::VOTING => 'votingAdmins',
            WorkgroupFunction::FSP => 'fspAdmins',
            WorkgroupFunction::STORES_COORDINATION => 'storesAdmins',
            WorkgroupFunction::REPORT => 'reportAdmins',
            WorkgroupFunction::MEDIATION => 'mediationAdmins',
            WorkgroupFunction::ARBITRATION => 'arbitrationAdmins',
            WorkgroupFunction::FSMANAGEMENT => 'fsManagementAdmins',
            WorkgroupFunction::PR => 'prAdmins',
            WorkgroupFunction::MODERATION => 'moderationAdmins',
            WorkgroupFunction::BOARD => 'boardAdmins',
            WorkgroupFunction::ELECTION => 'electionAdmins',
        ];

        foreach ($functionMappings as $function => $resultKey) {
            $region[$resultKey] = $this->getAdminsOrAmbassadorsByFunction($regionId, $function);
            shuffle($region[$resultKey]);
        }

        return $region;
    }

    private function getAdminsOrAmbassadorsByFunction(int $parentId, int $function): array
    {
        $groupId = $this->groupFunctionGateway->getRegionFunctionGroupId($parentId, $function);
        if ($groupId) {
            return $this->foodsaverGateway->getAdminsOrAmbassadors($groupId);
        } else {
            return [];
        }
    }

    /**
     * Return the unit type for a region.
     *
     * @see UnitType
     *
     * @throws Exception when region does not exist
     */
    public function getType(int $regionId): int
    {
        return (int)$this->db->fetchValueByCriteria('fs_bezirk', 'type', ['id' => $regionId]);
    }

    /**
     * Returns all users who have an pending application to the given region.
     *
     * @param int $regionId the region for which to list the applicants
     *
     * @return Profile[]
     *
     * @throws Exception
     */
    public function listApplicants(int $regionId): array
    {
        $applicants = $this->db->fetchAll('
			SELECT 	fs.`id`,
					fs.`name`,
					fs.`photo`,
					fs.sleep_status,
					fb.active

			FROM 	`fs_foodsaver_has_bezirk` fb,
					`fs_foodsaver` fs

			WHERE 	fb.foodsaver_id = fs.id
			AND 	fb.bezirk_id = :regionId
			AND 	fb.active = 0
		', ['regionId' => $regionId]);

        return array_map(fn ($applicant) => new Profile($applicant['id'], $applicant['name'], $applicant['photo'], $applicant['sleep_status']), $applicants);
    }

    public function linkBezirk(int $foodsaverId, int $regionId, int $active = 1)
    {
        $this->db->insertOrUpdate('fs_foodsaver_has_bezirk', [
            'bezirk_id' => $regionId,
            'foodsaver_id' => $foodsaverId,
            'added' => $this->db->now(),
            'active' => $active
        ]);
    }

    public function setRegionAdmin(int $regionId, int $fs_id)
    {
        $this->db->insert('fs_botschafter', [
            'bezirk_id' => $regionId,
            'foodsaver_id' => $fs_id
        ]);
    }

    public function removeRegionAdmin(int $regionId, int $fs_id)
    {
        $this->db->delete('fs_botschafter', [
            'bezirk_id' => $regionId,
            'foodsaver_id' => $fs_id
        ]);
    }

    public function update_bezirkNew(int $regionId, array $data)
    {
        if (isset($data['botschafter']) && is_array($data['botschafter'])) {
            $this->db->delete('fs_botschafter', ['bezirk_id' => $regionId]);
            foreach ($data['botschafter'] as $foodsaver_id) {
                $this->db->insert('fs_botschafter', [
                    'bezirk_id' => $regionId,
                    'foodsaver_id' => $foodsaver_id
                ]);
            }
        }

        $master = 0;
        if (isset($data['master'])) {
            $master = (int)$data['master'];
        }

        $this->db->beginTransaction();

        if ((int)$data['parent_id'] > RegionIDs::ROOT) {
            $this->db->update('fs_bezirk', ['has_children' => 1], ['id' => $data['parent_id']]);
        }

        $has_children = 0;
        if ($this->db->exists('fs_bezirk', ['parent_id' => $regionId])) {
            $has_children = 1;
        }

        $this->db->update(
            'fs_bezirk',
            [
                'name' => strip_tags($data['name']),
                'email_name' => strip_tags($data['email_name']),
                'parent_id' => $data['parent_id'],
                'type' => $data['type'],
                'master' => $master,
                'has_children' => $has_children,
            ],
            ['id' => $regionId]
        );

        $this->db->execute('DELETE a FROM `fs_bezirk_closure` AS a JOIN `fs_bezirk_closure` AS d ON a.bezirk_id = d.bezirk_id LEFT JOIN `fs_bezirk_closure` AS x ON x.ancestor_id = d.ancestor_id AND x.bezirk_id = a.ancestor_id WHERE d.ancestor_id = ' . $regionId . ' AND x.ancestor_id IS NULL');
        $this->db->execute('INSERT INTO `fs_bezirk_closure` (ancestor_id, bezirk_id, depth) SELECT supertree.ancestor_id, subtree.bezirk_id, supertree.depth+subtree.depth+1 FROM `fs_bezirk_closure` AS supertree JOIN `fs_bezirk_closure` AS subtree WHERE subtree.ancestor_id = ' . $regionId . ' AND supertree.bezirk_id = ' . (int)(int)$data['parent_id']);
        $this->db->commit();
    }

    public function addRegion(array $data): int
    {
        $this->db->beginTransaction();

        $id = $this->db->insert('fs_bezirk', [
            'parent_id' => (int)$data['parent_id'],
            'has_children' => (int)$data['has_children'],
            'name' => strip_tags($data['name']),
            'email' => strip_tags($data['email']),
            'email_pass' => strip_tags($data['email_pass']),
            'email_name' => strip_tags($data['email_name'])
        ]);

        $this->db->execute('INSERT INTO `fs_bezirk_closure` (ancestor_id, bezirk_id, depth) SELECT t.ancestor_id, ' . $id . ', t.depth+1 FROM `fs_bezirk_closure` AS t WHERE t.bezirk_id = ' . (int)$data['parent_id'] . ' UNION ALL SELECT ' . $id . ', ' . $id . ', 0');
        $this->db->commit();

        if (isset($data['foodsaver']) && is_array($data['foodsaver'])) {
            foreach ($data['foodsaver'] as $foodsaver_id) {
                $this->db->insert('fs_botschafter', [
                    'bezirk_id' => (int)$id,
                    'foodsaver_id' => (int)$foodsaver_id
                ]);
                $this->db->insert('fs_foodsaver_has_bezirk', [
                    'bezirk_id' => (int)$id,
                    'foodsaver_id' => (int)$foodsaver_id
                ]);
            }
        }

        return $id;
    }

    public function getRegionName(int $regionId): string
    {
        return $this->db->fetchValueByCriteria('fs_bezirk', 'name', ['id' => $regionId]);
    }

    public function addMember(int $foodsaverId, int $regionId)
    {
        $this->db->insertIgnore('fs_foodsaver_has_bezirk', [
            'foodsaver_id' => $foodsaverId,
            'bezirk_id' => $regionId,
            'active' => 1,
            'added' => $this->db->now()
        ]);
    }

    public function getMasterId(int $regionId): int
    {
        return $this->db->fetchValueByCriteria('fs_bezirk', 'master', ['id' => $regionId]);
    }

    public function listRegionsForBotschafter(int $foodsaverId): array
    {
        return $this->db->fetchAll(
            'SELECT 	`fs_botschafter`.`bezirk_id`,
					`fs_bezirk`.`has_children`,
					`fs_bezirk`.`parent_id`,
					`fs_bezirk`.name,
					`fs_bezirk`.id,
					`fs_bezirk`.type

			FROM 	`fs_botschafter`,
					`fs_bezirk`

			WHERE 	`fs_bezirk`.`id` = `fs_botschafter`.`bezirk_id`

			AND 	`fs_botschafter`.`foodsaver_id` = :id',
            [':id' => $foodsaverId]
        );
    }

    public function addOrUpdateMember(int $foodsaverId, int $regionId): bool
    {
        return $this->db->insertOrUpdate('fs_foodsaver_has_bezirk', [
            'foodsaver_id' => $foodsaverId,
            'bezirk_id' => $regionId,
            'active' => 1,
            'added' => $this->db->now()
        ]) > 0;
    }

    public function updateMasterRegions(array $regionIds, int $masterId): void
    {
        $this->db->update('fs_bezirk', ['master' => $masterId], ['id' => $regionIds]);
    }

    public function listRegionPickupsByDate(int $regionId, string $dateFormat): array
    {
        $regionIDs = implode(',', array_map('intval', $this->listIdsForDescendantsAndSelf($regionId)));

        if (empty($regionIDs)) {
            return [];
        }

        return $this->db->fetchAll(
            'select
						date_Format(a.date,:format) as time,
						count(distinct a.betrieb_id) as NumberOfStores,
						count(distinct a.date, a.betrieb_id) as NumberOfAppointments ,
						count(*) as NumberOfSlots,
						count(distinct a.foodsaver_id) as NumberOfFoodsavers
					from fs_abholer a
					left outer join fs_betrieb b on a.betrieb_id = b.id
						where b.bezirk_id in (' . $regionIDs . ')
					group by date_Format(date,:groupFormat)
					order by date desc',
            [':format' => $dateFormat, ':groupFormat' => $dateFormat]
        );
    }

    /**
     * Returns an option for the region, or null if the option is not set for the region.
     * See {@see RegionOptionType}.
     *
     * @param int $regionId ID of region
     * @param int $optionType type of option
     *
     * @return string|null value of option or null if not found
     *
     * @throws Exception
     */
    public function getRegionOption(int $regionId, int $optionType): ?string
    {
        try {
            return $this->db->fetchValueByCriteria('fs_region_options', 'option_value', [
                'region_id' => $regionId,
                'option_type' => $optionType
            ]);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Returns all options for the region as an array, or an empty array if no options are set for the region.
     *
     * @param int $regionId ID of region
     *
     * @return array associative array of options or empty array if not found
     *
     * @throws Exception
     */
    public function getRegionOptions(int $regionId): array
    {
        try {
            $optionTypes = [
                RegionOptionType::REGION_PICKUP_RULE_ACTIVE => 'regionPickupRuleActive',
                RegionOptionType::REGION_PICKUP_RULE_TIMESPAN_DAYS => 'regionPickupRuleTimespan',
                RegionOptionType::REGION_PICKUP_RULE_LIMIT_NUMBER => 'regionPickupRuleLimit',
                RegionOptionType::REGION_PICKUP_RULE_LIMIT_DAY_NUMBER => 'regionPickupRuleLimitDay',
                RegionOptionType::REGION_PICKUP_RULE_INACTIVE_HOURS => 'regionPickupRuleInactive'
            ];

            $options = $this->db->fetchAllByCriteria('fs_region_options', ['option_type', 'option_value'], [
                'region_id' => $regionId,
                'option_type' => array_keys($optionTypes)
            ]);

            $mappedOptions = [];
            foreach ($options as $option) {
                $optionType = $option['option_type'];
                $optionName = $optionTypes[$optionType];
                $optionValue = $optionType === RegionOptionType::REGION_PICKUP_RULE_ACTIVE ? (bool)$option['option_value'] : $option['option_value'];
                $mappedOptions[$optionName] = $optionValue;
            }

            return $mappedOptions;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Sets an option for the region. If the option is already existing for this region, it will be
     * overwritten. See {@see RegionOptionType}.
     */
    public function setRegionOption(int $regionId, int $optionType, string $value): void
    {
        $this->db->insertOrUpdate('fs_region_options', [
            'region_id' => $regionId,
            'option_type' => $optionType,
            'option_value' => $value,
        ]);
    }

    /**
     * Returns all option for the region
     * See {@see RegionOptionType}.
     *
     * @param int $regionId ID of region
     *
     * @return array|null value of option or null if not found
     *
     * @throws Exception
     */
    public function getAllRegionOptions(int $regionId): ?array
    {
        try {
            $result = $this->db->fetchAll('
			SELECT 	region_id as regionId,
			        option_type,
			        option_value
			FROM            `fs_region_options` ro
			WHERE    region_id = :regionId
		', [
                ':regionId' => $regionId,
            ]);
        } catch (Exception $e) {
            return null;
        }
        $optionTypeMap = [];
        foreach ($result as $key => $value) {
            $optionTypeMap[$value['option_type']] = $value['option_value'];
        }

        return $optionTypeMap;
    }

    public function getRegionPin(int $regionId): ?array
    {
        try {
            return $this->db->fetchByCriteria('fs_region_pin', ['desc', 'lat', 'lon', 'status'], ['region_id' => $regionId]);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Updates the values of a region's map marker.
     *
     * @param int $status see {@link RegionPinStatus}
     */
    public function setRegionPin(int $regionId, string $lat, string $lon, string $desc, int $status): void
    {
        $this->db->insertOrUpdate('fs_region_pin', [
            'region_id' => $regionId,
            'lat' => $lat,
            'lon' => $lon,
            'desc' => $desc,
            'status' => $status
        ]);
    }

    public function hasSubgroups(int $regionId): bool
    {
        $parentalStatus = $this->db->fetchByCriteria('fs_bezirk', ['has_children'], ['id' => $regionId]);
        if (empty($parentalStatus)) {
            return false;
        }

        $hasSubgroup = (bool)$parentalStatus['has_children'];

        return $hasSubgroup;
    }

    public function updateRegionNotification(int $foodsaverId, int $regionId, bool $notifyByEmail): void
    {
        $this->db->update(
            'fs_foodsaver_has_bezirk',
            ['notify_by_email_about_new_threads' => $notifyByEmail ? 1 : 0],
            [
                'foodsaver_id' => $foodsaverId,
                'bezirk_id' => $regionId,
            ]
        );
    }
}
