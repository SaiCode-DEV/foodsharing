<?php

namespace Foodsharing\Modules\Region;

use Exception;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;
use Foodsharing\Modules\Core\DBConstants\Region\ApplyType;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Region\RegionOptionType;
use Foodsharing\Modules\Core\DBConstants\Store\CooperationStatus;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\Region\DTO\BasicRegionStatistics;
use Foodsharing\Modules\Region\DTO\HierachicalRegion;
use Foodsharing\Modules\Region\DTO\MinimalRegionIdentifier;
use Foodsharing\Modules\Region\DTO\PublicRegionData;
use Foodsharing\Modules\Region\DTO\RegionPickupsPerDate;
use Foodsharing\Modules\Region\DTO\RegionPin;
use Foodsharing\RestApi\Models\Region\RegionForAdministration;

class RegionGateway extends BaseGateway
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function getRegion(int $regionId): ?array
    {
        if ($regionId == RegionIDs::ROOT) {
            return null;
        }

        return $this->db->fetchByCriteria('fs_bezirk',
            ['name', 'id', 'email_name', 'has_children', 'parent_id', 'mailbox_id', 'type'],
            ['id' => $regionId]
        );
    }

    public function listRegionsIncludingParents(array $regionId): array
    {
        $stm = 'SELECT DISTINCT ancestor_id FROM `fs_bezirk_closure` WHERE bezirk_id IN (' . implode(',', array_map('intval', $regionId)) . ')';

        return $this->db->fetchAllValues($stm);
    }

    public function getRegionByParent(int $parentId, bool $includeWorkgroups = false): array
    {
        $typeClause = 'AND `type` != ' . UnitType::WORKING_GROUP;
        if ($includeWorkgroups) {
            $typeClause = '';
        }

        return $this->db->fetchAll("SELECT
				`id`,
				`name`,
				`has_children` as hasChildren,
				`type`
			FROM `fs_bezirk`
			WHERE `parent_id` = :id
			AND id != :rootId
			{$typeClause}
			ORDER BY IF(`type` = :workingGroupType, 1, 0), `name`",
            [
                ':id' => $parentId,
                ':rootId' => RegionIDs::ROOT,
                ':workingGroupType' => UnitType::WORKING_GROUP,
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
     *
     * @return array an empty array if it does not exist
     */
    public function getRegionDetails(int $regionId): array
    {
        $region = $this->db->fetch('
			SELECT
				b.`id`,
			    b.parent_id,
				b.`name`,
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
				) AS fs_count,
				(
					SELECT 	count(c.`foodsaver_id`)
					FROM 	`fs_foodsaver_has_bezirk` c
					LEFT JOIN `fs_foodsaver` fs ON c.`foodsaver_id` = fs.id
					WHERE   fs.deleted_at IS NULL
                    AND     fs.bezirk_id = c.bezirk_id
					AND 	c.bezirk_id = b.id
					AND 	c.active = 1
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
        if (empty($region)) {
            return [];
        }

        return $region;
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
     */
    public function listApplicants(int $regionId): array
    {
        $applicants = $this->db->fetchAll('
			SELECT 	fs.`id`,
					fs.`name`,
					fs.`photo`,
					fs.is_sleeping,
					fb.active

			FROM 	`fs_foodsaver_has_bezirk` fb,
					`fs_foodsaver` fs

			WHERE 	fb.foodsaver_id = fs.id
			AND 	fb.bezirk_id = :regionId
			AND 	fb.active = 0
		', ['regionId' => $regionId]);

        return array_map(fn ($applicant) => new Profile($applicant), $applicants);
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

    // TODO move all non-WG-secific methods in GroupGateway to regionGateway

    /**
     * @throws DatabaseNoValueFoundException if the region does not exist
     */
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

    public function getParentId(int $regionId): int
    {
        return $this->db->fetchValueByCriteria('fs_bezirk', 'parent_id', ['id' => $regionId]);
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

    /**
     * Returns a region's pickup statistics for one specific date format. This includes all subregions.
     *
     * @return RegionPickupsPerDate[]
     */
    public function listRegionPickupsByDate(int $regionId, string $dateFormat): array
    {
        $regionIDs = implode(',', array_map('intval', $this->listIdsForDescendantsAndSelf($regionId)));

        if (empty($regionIDs)) {
            return [];
        }

        $data = $this->db->fetchAll(
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

        return array_map(RegionPickupsPerDate::createFromArray(...), $data);
    }

    /**
     * Returns an option for the region, or null if the option is not set for the region.
     * See {@see RegionOptionType}.
     *
     * @param int $regionId ID of region
     * @param int $optionType type of option
     *
     * @return string|null value of option or null if not found
     */
    public function getRegionOption(int $regionId, int $optionType): ?string
    {
        try {
            return $this->db->fetchValueByCriteria('fs_region_options', 'option_value', [
                'region_id' => $regionId,
                'option_type' => $optionType
            ]);
        } catch (Exception) {
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
     * @deprecated This does not actually return all options, but only five specific types. It should be replaced by getAllRegionOptions.
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
        } catch (Exception) {
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
        } catch (Exception) {
            return null;
        }
        $optionTypeMap = [];
        foreach ($result as $key => $value) {
            $optionTypeMap[$value['option_type']] = $value['option_value'];
        }

        return $optionTypeMap;
    }

    public function getRegionPin(int $regionId): ?RegionPin
    {
        try {
            $data = $this->db->fetchByCriteria('fs_region_pin', ['desc', 'lat', 'lon', 'status'], ['region_id' => $regionId]);

            return RegionPin::create($data);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Updates the given values of a region's map marker.
     *
     * @param int $status see {@link RegionPinStatus}
     */
    public function setRegionPin(?int $regionId, ?string $lat, ?string $lon, ?string $desc, ?int $status): void
    {
        if (!is_null($lat)) {
            $this->db->insertOrUpdate('fs_region_pin', [
                'region_id' => $regionId,
                'lat' => $lat,
                'lon' => $lon,
            ]);
        }
        if (!is_null($desc)) {
            $this->db->insertOrUpdate('fs_region_pin', [
                'region_id' => $regionId,
                'desc' => $desc,
            ]);
        }
        if (!is_null($status)) {
            $this->db->insertOrUpdate('fs_region_pin', [
                'region_id' => $regionId,
                'status' => $status,
            ]);
        }
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

    public function getRegionsForCommunitiesContacts(): array
    {
        $host = PLATFORM_MAILBOX_HOST;
        $data = $this->db->fetchAll("SELECT
                region.name,
                region.id,
                region.parent_id AS parentId,
                mailbox.name AS email,
                NOT ISNULL(ambassador.foodsaver_id) AS hasAmbassador
            FROM fs_mailbox mailbox
            JOIN fs_bezirk region ON region.mailbox_id = mailbox.id
            LEFT OUTER JOIN fs_botschafter ambassador ON ambassador.bezirk_id = region.id
            WHERE region.type != :workingGroup
            GROUP BY region.id
            ORDER BY
                IF(region.type = :country, '', region.name) ASC, # order overything but countries by size
                region.stat_fscount DESC # order countries by size
        ", [
            ':workingGroup' => UnitType::WORKING_GROUP,
            ':country' => UnitType::COUNTRY,
        ]);

        return array_map(HierachicalRegion::createFromArray(...), $data);
    }

    public function getRegionForEditing(int $regionId): array
    {
        $region = $this->db->fetchById('fs_bezirk',
            ['id', 'name', 'parent_id', 'master', 'type', 'mailbox_id', 'email_name'],
            $regionId
        );
        $region['adminIds'] = $this->db->fetchAllValuesByCriteria('fs_botschafter', 'foodsaver_id', ['bezirk_id' => $regionId]);

        return $region;
    }

    public function editRegion(RegionForAdministration $region): void
    {
        $this->db->beginTransaction();
        $oldParentId = $this->db->fetchValueById('fs_bezirk', 'parent_id', $region->id);
        $this->db->update('fs_bezirk', [
            'name' => $region->name,
            'email_name' => $region->emailName,
            'parent_id' => $region->parentId,
            'master' => $region->masterId,
            'type' => $region->type
        ], ['id' => $region->id]);
        $this->removeRegionFromClosure($region->id);
        $this->addRegionToClosure($region->id, $region->parentId);

        // update hasChildren values for old and new parent
        $oldParentHasChildren = $this->db->exists('fs_bezirk_closure', ['ancestor_id' => $oldParentId, 'depth' => 1]);
        $this->db->update('fs_bezirk', ['has_children' => $oldParentHasChildren], ['id' => $oldParentId]);
        $this->db->update('fs_bezirk', ['has_children' => true], ['id' => $region->parentId]);

        $this->db->commit();
    }

    public function addRegion(RegionForAdministration $region): int
    {
        $this->db->beginTransaction();
        $region->id = $this->db->insert('fs_bezirk', [
            'name' => $region->name,
            'email_name' => $region->emailName,
            'parent_id' => $region->parentId,
            'type' => $region->type,
            'apply_type' => ApplyType::NOBODY,
        ], ['id' => $region->id]);
        $this->addRegionToClosure($region->id, $region->parentId);
        $this->db->update('fs_bezirk', ['has_children' => true], ['id' => $region->parentId]);
        $this->db->commit();

        return $region->id;
    }

    public function regionHasAncestor(int $regionId, int $ancestorId): bool
    {
        return $this->db->exists('fs_bezirk_closure', [
            'bezirk_id' => $regionId,
            'ancestor_id' => $ancestorId,
        ]);
    }

    public function setRegionAdmins(int $regionId, array $adminIds): void
    {
        $this->db->delete('fs_botschafter', ['bezirk_id' => $regionId]);
        foreach ($adminIds as $adminId) {
            $this->setRegionAdmin($regionId, $adminId);
        }
    }

    private function removeRegionFromClosure(int $regionId): void
    {
        $this->db->execute('DELETE a
            FROM `fs_bezirk_closure` AS a
            JOIN `fs_bezirk_closure` AS d ON a.bezirk_id = d.bezirk_id
            LEFT JOIN `fs_bezirk_closure` AS x ON x.ancestor_id = d.ancestor_id AND x.bezirk_id = a.ancestor_id
            WHERE d.ancestor_id = ? AND x.ancestor_id IS NULL',
            [$regionId]
        );
    }

    private function addRegionToClosure(int $regionId, int $parentId): void
    {
        $this->db->execute('INSERT INTO `fs_bezirk_closure`
                (ancestor_id, bezirk_id, depth)
            SELECT supertree.ancestor_id, subtree.bezirk_id, supertree.depth+subtree.depth+1
            FROM `fs_bezirk_closure` AS supertree
            JOIN `fs_bezirk_closure` AS subtree
            WHERE subtree.ancestor_id = :regionId AND supertree.bezirk_id = :parentId', [
                ':regionId' => $regionId,
                ':parentId' => $parentId,
        ]);
    }

    public function getPublicRegionBasics(int $regionId): ?PublicRegionData
    {
        $data = $this->db->fetch('SELECT
                region.`id`, region.`name`, region.`type`,
                pin.`lat`, pin.`lon`, pin.`desc`, pin.`status`,
                mail.`name` AS email,
                NOT ISNULL(ambassador.foodsaver_id) AS hasAmbassador
            FROM fs_bezirk region
            LEFT OUTER JOIN fs_region_pin pin ON pin.region_id = region.id
            LEFT OUTER JOIN fs_mailbox mail ON mail.id = region.mailbox_id
            LEFT OUTER JOIN fs_botschafter ambassador ON ambassador.bezirk_id = region.id
            WHERE region.`id` = :id
        ', ['id' => $regionId]);

        return PublicRegionData::tryCreateFrom($data);
    }

    public function getRegionAncestors(int $regionId): array
    {
        $ancestors = $this->db->fetchAll('SELECT
                r.id, r.name
            FROM fs_bezirk_closure c
            JOIN fs_bezirk r ON r.id = c.ancestor_id
            WHERE c.bezirk_id = :id AND depth > 0 AND c.ancestor_id != 0
            ORDER BY depth DESC
        ', ['id' => $regionId]);

        return array_map(fn ($region) => MinimalRegionIdentifier::create($region['id'], $region['name']), $ancestors);
    }

    /**
     * Returns all ancestors of a region, inlcuding information about whether the given user is member of that region.
     */
    public function getRegionAncestorMemberships(int $regionId, int $foodsaverId): array
    {
        return $this->db->fetchAll('SELECT
                r.id, r.name, r.type, m.active IS NOT NULL AS is_member
            FROM fs_bezirk_closure c
            JOIN fs_bezirk r ON r.id = c.ancestor_id
            LEFT OUTER JOIN fs_foodsaver_has_bezirk m ON m.foodsaver_id = :foodsaverId AND m.active = 1 AND m.bezirk_id = r.id
            WHERE c.bezirk_id = :regionId AND c.ancestor_id != 0
            ORDER BY depth ASC
        ', ['regionId' => $regionId, 'foodsaverId' => $foodsaverId]);
    }

    public function getRegionChildren(int $regionId): array
    {
        $children = $this->db->fetchAll('SELECT
                r.id, r.name
            FROM fs_bezirk_closure c
            JOIN fs_bezirk r ON r.id = c.bezirk_id
            WHERE c.ancestor_id = :id AND depth = 1 AND r.type != :workingGroupType
            ORDER BY r.name ASC
        ', ['id' => $regionId, 'workingGroupType' => UnitType::WORKING_GROUP]);

        return array_map(fn ($region) => MinimalRegionIdentifier::create($region['id'], $region['name']), $children);
    }

    public function getBasicRegionStatistics(int $regionId): BasicRegionStatistics
    {
        $stats = new BasicRegionStatistics();

        $pickupData = $this->db->fetch('SELECT
                COUNT(*) as count, SUM(w.weight) AS weight
            FROM fs_bezirk_closure c
            JOIN fs_bezirk r ON r.id = c.bezirk_id
            JOIN fs_betrieb s ON s.bezirk_id = r.id
            JOIN fs_abholer a ON a.betrieb_id = s.id
            JOIN fs_fetchweight w ON w.id = s.abholmenge
            WHERE c.ancestor_id = :id
            AND a.date >= (NOW() - INTERVAL 30 DAY) AND a.date < NOW()
        ', ['id' => $regionId]);
        $stats->pickupsLastMonth = $pickupData['count'];
        $stats->savedFoodKgLastMonth = intval(round($pickupData['weight']));

        $stats->activeHomeRegionFoodsavers = $this->db->fetchValue('SELECT
                COUNT(*)
            FROM fs_bezirk_closure c
            JOIN fs_foodsaver fs ON fs.bezirk_id = c.bezirk_id
            WHERE c.ancestor_id = :id
            AND fs.deleted_at IS NULL
            AND fs.last_login > NOW() - INTERVAL 30 DAY
            AND fs.verified = 1
        ', ['id' => $regionId]);

        $stats->activeCoorporations = $this->db->fetchValue('SELECT
                COUNT(*)
            FROM fs_bezirk_closure c
            JOIN fs_betrieb s ON s.bezirk_id = c.bezirk_id
            WHERE c.ancestor_id = :id
            AND s.betrieb_status_id = :cooperating_status
        ', [
            'id' => $regionId,
            'cooperating_status' => CooperationStatus::COOPERATION_ESTABLISHED->value
        ]);

        $stats->activeFoodSharePoints = $this->db->fetchValue('SELECT
                COUNT(*)
            FROM fs_bezirk_closure c
            JOIN fs_fairteiler fsp ON fsp.bezirk_id = c.bezirk_id
            WHERE c.ancestor_id = :id
            AND fsp.status = 1
        ', ['id' => $regionId]);

        $stats->foodBasketsLastMonth = $this->db->fetchValue('SELECT
				COUNT(*)
            FROM fs_bezirk_closure c
            JOIN fs_basket b ON b.bezirk_id = c.bezirk_id
            WHERE c.ancestor_id = :id
            AND b.time >= (NOW() - INTERVAL 30 DAY) AND b.time < NOW()
        ', ['id' => $regionId]);

        return $stats;
    }

    public function getRegionIdByMail(string $mailboxName): int
    {
        return (int)$this->db->fetchValue('SELECT region.id
            FROM fs_bezirk region
            JOIN fs_mailbox mailbox ON mailbox.id = region.mailbox_id
            WHERE mailbox.name = ?', [$mailboxName]);
    }
}
